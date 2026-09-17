<?php

declare(strict_types=1);

namespace app\service\wechat;

use app\repository\user\UserRepository;
use app\service\message\MessageService;
use app\service\user\UserSessionIssuer;
use core\base\Service;
use core\contract\ConfigValueReader;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatException;
use core\wechat\exception\WechatNotConfiguredException;
use core\wechat\exception\WechatUnavailableException;
use core\wechat\MiniProgramApi;
use core\wechat\OAuthApi;
use core\wechat\WechatConfigResolver;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Log;
use support\Redis;

/**
 * C 端微信登录（M6a spec §4）。
 *
 * 账号匹配（§4.1）：先按本端 openid 列找；没有再按 unionid 找，那个账号本端列为空才补写，已是别的值一律视为未命中
 * ——绝不覆盖已有绑定（1.x 在这里静默覆盖，等于让同一个 unionid 的另一端把账号接管走）。
 *
 * 并发：同一 {列}:{openid} 的「匹配 → 绑定 / 注册」包在 Redis 锁里（SET NX EX，随机值，Lua 比较后删除），
 * 首次登录连点两次不会注册出两个账号。库里 openid 列没有唯一索引（给存量表加唯一索引要 ALTER，软删的账号还会挡住
 * 重新注册），所以唯一性靠这把锁。抢不到等 200ms 再试一次，仍抢不到回 429。
 * 网络调用（换 code、取昵称头像、解密手机号）一律放在锁外：锁只有几秒，慢请求不能把锁拖过期。
 *
 * 微信的三类异常在 wechat() 里统一翻成业务错误；errcode 只进 warning 日志，code / secret / session_key
 * 不进日志也不进响应。
 */
class WechatAuthService extends Service
{
    private const LOCK_PREFIX = 'wechat:login_lock:';

    private const LOCK_RETRY_MICROSECONDS = 200_000;

    private const RELEASE_LOCK = "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) end return 0";

    /** 快捷登录中间态凭证的 Redis 键前缀（红线测试引用） */
    public const QUICK_KEY_PREFIX = 'wechat:quick:';

    private const TEMP_TOKEN_PATTERN = '/^[0-9a-f]{32}$/';

    private const GET_AND_DELETE = "local v = redis.call('GET', KEYS[1]) if v then redis.call('DEL', KEYS[1]) end return v";

    /** users.avatar 是 varchar(255)：更长的头像地址宁可不存，也不能让插入报错把登录打断 */
    private const AVATAR_MAX_LENGTH = 255;

    /** users.nickname 是 varchar(50) */
    private const NICKNAME_MAX_LENGTH = 50;

    #[Inject]
    protected WechatConfigResolver $wechatConfig;

    #[Inject]
    protected MiniProgramApi $miniProgram;

    #[Inject]
    protected OAuthApi $oauth;

    #[Inject]
    protected UserRepository $users;

    #[Inject]
    protected UserSessionIssuer $sessions;

    #[Inject]
    protected ConfigValueReader $config;

    #[Inject]
    protected MessageService $messageService;

    /**
     * PC 扫码登录（spec §4.2）：开放平台换 code → 按 openid 列匹配 → 未命中尽力取昵称头像后注册。
     *
     * @return array{token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}}
     * @throws BusinessException 未配置 / 授权失败 / 服务不可用 / 账户禁用 / 锁冲突（429）
     */
    public function webLogin(string $code, string $ip): array
    {
        $identity = $this->wechat('sns/oauth2/access_token', fn (): array => $this->oauth->exchangeCode($this->wechatConfig->open(), $code));

        // 可能要注册时才取昵称头像，而且放在锁外：sns/userinfo 读超时 10 秒，比锁长
        $profile = null;
        if ($this->users->findByWechatColumn('openid', $identity['openid']) === null) {
            $profile = $this->bestEffortProfile($identity['access_token'], $identity['openid']);
        }

        $userId = $this->withLoginLock('openid', $identity['openid'], function () use ($identity, $profile): int {
            $user = $this->matchOrBind('openid', $identity['openid'], $identity['unionid']);
            if ($user !== null) {
                return $this->activeUserId($user);
            }
            $profile ??= ['nickname' => $this->defaultNickname(), 'avatar' => null];

            return $this->register('openid', $identity['openid'], $identity['unionid'], $profile['nickname'], $profile['avatar'], null);
        });

        return $this->sessions->issue($userId, $ip);
    }

    /**
     * 小程序静默登录（spec §4.3）：code2Session → 按 mini_openid 列匹配 → 未命中直接注册。session_key 在 core 层已丢弃。
     *
     * @return array{token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}}
     * @throws BusinessException
     */
    public function miniLogin(string $code, string $ip): array
    {
        $identity = $this->wechat('sns/jscode2session', fn (): array => $this->miniProgram->code2Session($this->wechatConfig->mini(), $code));

        $userId = $this->withLoginLock('mini_openid', $identity['openid'], function () use ($identity): int {
            $user = $this->matchOrBind('mini_openid', $identity['openid'], $identity['unionid']);
            if ($user !== null) {
                return $this->activeUserId($user);
            }

            return $this->register('mini_openid', $identity['openid'], $identity['unionid'], $this->defaultNickname(), null, null);
        });

        return $this->sessions->issue($userId, $ip);
    }

    /**
     * 小程序快捷登录（spec §4.4）：命中直接登录；未命中返回一次性 temp_token，由 bindPhone 用手机号完成注册或绑定。
     *
     * @return array{status: 'logged_in', token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}}|array{status: 'need_bindphone', temp_token: string}
     * @throws BusinessException
     */
    public function quickLogin(string $code, string $ip): array
    {
        $identity = $this->wechat('sns/jscode2session', fn (): array => $this->miniProgram->code2Session($this->wechatConfig->mini(), $code));

        $userId = $this->withLoginLock('mini_openid', $identity['openid'], function () use ($identity): ?int {
            $user = $this->matchOrBind('mini_openid', $identity['openid'], $identity['unionid']);

            return $user === null ? null : $this->activeUserId($user);
        });

        if ($userId !== null) {
            return ['status' => 'logged_in'] + $this->sessions->issue($userId, $ip);
        }

        $tempToken = bin2hex(random_bytes(16));
        Redis::set(
            self::QUICK_KEY_PREFIX . $tempToken,
            json_encode(['openid' => $identity['openid'], 'unionid' => $identity['unionid']], JSON_THROW_ON_ERROR),
            'EX',
            max(1, (int) config('wechat.quick_token_ttl', 300))
        );

        return ['status' => 'need_bindphone', 'temp_token' => $tempToken];
    }

    /**
     * 绑手机号（spec §4.5）。temp_token 一次性：先原子取出并删除，再解密手机号——解密失败也不能重放，用户需重新快捷登录。
     *
     * 手机号已有账号：mini_openid 为空 → 补写；等于本 openid → 直接登录；是别的值 → 拒绝（不覆盖）。
     * 若本 openid 在 quickLogin 之后已被另一个账号占用，同样拒绝，不让同一 mini_openid 挂到两个账号上。
     * 手机号没有账号：锁内再按 mini_openid 查一次（防并发已注册），有则登录，无则带手机号注册——
     * 但 users.mobile 有唯一键且不含 deleted_at，软删会员仍占着这个键，findByAccount() 经软删作用域看不到它，
     * 注册前额外查一次含软删行（mobileTakenIncludingTrashed），命中就拒绝而不是让唯一键冲突捅穿到 HTTP 500
     * （那样还会把手机号明文带进错误日志）；两个不同 openid 并发抢同一手机号时上面那次查询本身防不住竞态，
     * 唯一键冲突兜底转成同一条业务错误，日志只记异常类名。
     *
     * @return array{status: 'logged_in', token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}}
     * @throws BusinessException
     */
    public function bindPhone(string $tempToken, string $phoneCode, string $ip): array
    {
        $pending = $this->consumeQuickToken($tempToken) ?? throw new BusinessException(lang('wechat.quick_expired'));
        $mobile = $this->wechat('wxa/business/getuserphonenumber', fn (): string => $this->miniProgram->getPhoneNumber($this->wechatConfig->mini(), $phoneCode));
        $openid = $pending['openid'];
        $unionid = $pending['unionid'];

        $userId = $this->withLoginLock('mini_openid', $openid, function () use ($mobile, $openid, $unionid): int {
            $user = $this->users->findByAccount($mobile);
            if ($user === null) {
                $existing = $this->users->findByWechatColumn('mini_openid', $openid);
                if ($existing !== null) {
                    return $this->activeUserId($existing);
                }
                if ($this->users->mobileTakenIncludingTrashed($mobile)) {
                    throw new BusinessException(lang('wechat.phone_unavailable'));
                }

                try {
                    return $this->register('mini_openid', $openid, $unionid, $this->defaultNickname(), null, $mobile);
                } catch (UniqueConstraintViolationException $e) {
                    // 两个不同 openid 并发绑同一手机号：上面那次查询挡不住竞态，唯一键冲突在这里兜底。
                    // 只记异常类名，QueryException::getMessage() 会带 SQL 与绑定值（手机号明文）。
                    Log::error('注册微信用户时手机号唯一键冲突', ['exception' => $e::class]);

                    throw new BusinessException(lang('wechat.phone_unavailable'));
                }
            }

            $userId = $this->activeUserId($user);
            $current = $user['mini_openid'] ?? null;
            if ($current !== null && $current !== $openid) {
                throw new BusinessException(lang('wechat.phone_bound_other'));
            }
            if ($current === null) {
                $owner = $this->users->findByWechatColumn('mini_openid', $openid);
                if ($owner !== null && (int) $owner['id'] !== $userId) {
                    throw new BusinessException(lang('wechat.phone_bound_other'));
                }
                if (!$this->users->bindWechatColumnIfEmpty($userId, 'mini_openid', $openid)
                    && ($this->users->find($userId)['mini_openid'] ?? null) !== $openid) {
                    throw new BusinessException(lang('wechat.phone_bound_other'));
                }
            }
            if ($unionid !== null && $this->users->findByUnionid($unionid) === null) {
                $this->users->fillUnionidIfEmpty($userId, $unionid);
            }

            return $userId;
        });

        return ['status' => 'logged_in'] + $this->sessions->issue($userId, $ip);
    }

    /**
     * 公众号静默登录（spec §4.6）。已绑定 → 登录；未绑定 → need_login（不注册），绑定证明 cookie 由控制器下发。
     *
     * @return array{status: 'logged_in', openid: string, unionid: ?string, token: string, user_info: array<string, mixed>}|array{status: 'need_login', openid: string, unionid: ?string}
     */
    public function h5Login(string $code, string $ip): array
    {
        $identity = $this->wechat('sns/oauth2/access_token', fn (): array => $this->oauth->exchangeCode($this->wechatConfig->official(), $code));
        $openid = $identity['openid'];
        $unionid = $identity['unionid'];

        $user = $this->withLoginLock('oa_openid', $openid, fn (): ?array => $this->matchOrBind('oa_openid', $openid, $unionid));
        if ($user === null) {
            return ['status' => 'need_login', 'openid' => $openid, 'unionid' => $unionid];
        }
        $userId = $this->activeUserId($user);

        return ['status' => 'logged_in', 'openid' => $openid, 'unionid' => $unionid] + $this->sessions->issue($userId, $ip);
    }

    /**
     * 公众号网页授权地址（spec §4.7）。scope 白名单由控制器校验；回调地址必须与 site_url 同 scheme、host、port。
     *
     * @throws ValidationException redirect_url 不在本站
     * @throws BusinessException   公众号未配置
     */
    public function oauthUrl(string $redirectUrl, string $scope): string
    {
        if (!$this->isSameSite($redirectUrl)) {
            throw new ValidationException(['redirect_url' => lang('wechat.redirect_not_allowed')]);
        }
        $appId = $this->wechat('oauth-url', fn (): string => $this->wechatConfig->official()->appId);

        return $this->oauth->authorizeUrl($appId, $redirectUrl, $scope);
    }

    /**
     * 登录后绑定公众号 openid（spec §4.8）。$proofOpenid 是控制器从 HttpOnly cookie 校验出的 openid——
     * 请求体里的 openid 只有与它相等才可信；无证明一律拒绝。锁内判定，不覆盖任何已有绑定。
     */
    public function bindOaOpenid(int $userId, string $openid, ?string $proofOpenid): void
    {
        if ($proofOpenid === null || $openid === '' || !hash_equals($proofOpenid, $openid)) {
            throw new BusinessException(lang('wechat.oa_bind_invalid'));
        }

        $this->withLoginLock('oa_openid', $openid, function () use ($userId, $openid): void {
            $me = $this->users->find($userId) ?? throw new BusinessException(lang('wechat.oa_bind_invalid'));
            $current = (string) ($me['oa_openid'] ?? '');
            if ($current === $openid) {
                return;
            }
            if ($current !== '') {
                throw new BusinessException(lang('wechat.account_bound_other_wechat'));
            }
            $owner = $this->users->findByWechatColumn('oa_openid', $openid);
            if ($owner !== null && (int) $owner['id'] !== $userId) {
                throw new BusinessException(lang('wechat.wechat_bound_other_account'));
            }
            if (!$this->users->bindWechatColumnIfEmpty($userId, 'oa_openid', $openid)) {
                // 锁外被别的请求抢先写了本账号的列：按「当前账号已绑定」处理，不覆盖
                throw new BusinessException(lang('wechat.account_bound_other_wechat'));
            }
        });
    }

    /** 同 scheme、同 host（忽略大小写）、同 port（缺省按 scheme 补 80/443）；site_url 为空或解析失败一律拒绝。拒绝 userinfo 伪装 */
    private function isSameSite(string $url): bool
    {
        $target = self::origin($url);
        $site = self::origin(trim((string) $this->config->getConfigValue('site_url', '')));

        return $target !== null && $site !== null && $target === $site;
    }

    /** @return ?string "scheme://host:port"；非绝对 http(s) 地址、带 user/pass 返回 null */
    private static function origin(string $url): ?string
    {
        if ($url === '') {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return "{$scheme}://{$host}:{$port}";
    }

    /**
     * 把 core/wechat 的三类异常翻成对客户端的业务错误（计划设计决定 3）。
     * 日志只记接口名、errcode、异常类名：异常消息里虽不含 URL，也不把它原样写进日志，免得日后有人在 core 层加内容时泄漏。
     *
     * @template T
     * @param \Closure(): T $fn
     * @return T
     */
    private function wechat(string $api, \Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (WechatNotConfiguredException) {
            throw new BusinessException(lang('wechat.not_configured'));
        } catch (WechatApiException $e) {
            Log::warning('微信接口拒绝请求', ['api' => $api, 'errcode' => $e->getErrcode()]);

            throw new BusinessException(lang('wechat.auth_failed'));
        } catch (WechatUnavailableException $e) {
            Log::warning('微信接口暂不可用', ['api' => $api, 'exception' => $e::class]);

            throw new BusinessException(lang('wechat.unavailable'));
        }
    }

    /**
     * 原子取出并删除中间态（Lua GET + DEL，先例 WsTicketService::consume()）。格式非法、不存在、已用过、载荷损坏都返回 null。
     *
     * @return array{openid: string, unionid: ?string}|null
     */
    private function consumeQuickToken(string $tempToken): ?array
    {
        if (preg_match(self::TEMP_TOKEN_PATTERN, $tempToken) !== 1) {
            return null;
        }
        $raw = Redis::eval(self::GET_AND_DELETE, 1, self::QUICK_KEY_PREFIX . $tempToken);
        if (!is_string($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !is_string($data['openid'] ?? null) || $data['openid'] === '') {
            return null;
        }
        $unionid = $data['unionid'] ?? null;

        return ['openid' => $data['openid'], 'unionid' => is_string($unionid) && $unionid !== '' ? $unionid : null];
    }

    /**
     * 账号匹配（spec §4.1）。必须在该 {列}:{openid} 的登录锁内调用。
     *
     * @return array<string, mixed>|null 命中的用户行；null 表示需要注册或返回中间态
     */
    private function matchOrBind(string $column, string $openid, ?string $unionid): ?array
    {
        $user = $this->users->findByWechatColumn($column, $openid);
        if ($user !== null || $unionid === null || $unionid === '') {
            return $user;
        }

        $candidate = $this->users->findByUnionid($unionid);
        if ($candidate === null || ($candidate[$column] ?? null) !== null) {
            // 本端列已经是别的 openid（相同的情况在上一步就命中了）：不覆盖，视为未命中
            return null;
        }

        $candidateId = (int) $candidate['id'];
        if ($this->users->bindWechatColumnIfEmpty($candidateId, $column, $openid)) {
            return $this->users->find($candidateId);
        }

        // 条件更新没写进去：这一瞬间别处给这个账号写了本端列（不同 openid 不会共用这把锁）。重读，恰好是本 openid 才算命中
        $fresh = $this->users->find($candidateId);

        return ($fresh !== null && ($fresh[$column] ?? null) === $openid) ? $fresh : null;
    }

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T
     * @throws BusinessException code 429：锁被占用
     */
    private function withLoginLock(string $column, string $openid, \Closure $fn): mixed
    {
        $key = self::LOCK_PREFIX . $column . ':' . $openid;
        $token = bin2hex(random_bytes(16));
        $ttl = max(1, (int) config('wechat.login_lock_seconds', 5));

        if (!$this->acquireLock($key, $token, $ttl)) {
            usleep(self::LOCK_RETRY_MICROSECONDS);
            if (!$this->acquireLock($key, $token, $ttl)) {
                throw new BusinessException(lang('wechat.too_frequent'), 429);
            }
        }

        try {
            return $fn();
        } finally {
            $this->releaseLock($key, $token);
        }
    }

    /** @phpstan-impure 每次调用都真的去 Redis 执行一次 SET NX，结果不能沿用上一次 */
    private function acquireLock(string $key, string $token, int $ttl): bool
    {
        return Redis::set($key, $token, 'EX', $ttl, 'NX') === true;
    }

    /** 比较后删除：锁过期后被别人抢到时，不删别人的锁。释放失败只记 error，不能盖掉 finally 前的原始异常。 */
    private function releaseLock(string $key, string $token): void
    {
        try {
            Redis::eval(self::RELEASE_LOCK, 1, $key, $token);
        } catch (\Throwable $e) {
            Log::error('释放微信登录锁失败', ['exception' => $e::class]);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @throws BusinessException 账户已禁用（与 M5a 同一文案）
     */
    private function activeUserId(array $user): int
    {
        if ((int) ($user['status'] ?? 0) !== 1) {
            throw new BusinessException(lang('auth.account_disabled'));
        }

        return (int) $user['id'];
    }

    /**
     * 注册微信用户。unionid 已被其他账号持有时不写（spec §4.1 把「unionid 命中但本端列为他值」视为未命中，
     * 此时再写同一 unionid 会让它挂到两个账号上）。
     */
    private function register(string $column, string $openid, ?string $unionid, string $nickname, ?string $avatar, ?string $mobile): int
    {
        if ($unionid === '' || ($unionid !== null && $this->users->findByUnionid($unionid) !== null)) {
            $unionid = null;
        }

        $userId = $this->users->createWechatUser($column, $openid, $unionid, $nickname, $avatar, $mobile);
        // M6b spec §4.8：PC 网页登录、小程序静默登录、bindPhone 新注册都经这里。不在 DB 事务里，afterCommit 立即执行
        // （仍在登录锁内，sendToUser 只写库、投递队列，不调外部接口）；它不抛异常，bindPhone 的唯一键兜底 catch 不受影响
        $this->afterCommit(fn () => $this->messageService->sendToUser($userId, 'user_register', ['nickname' => $nickname]));

        return $userId;
    }

    /** 落库文案固定中文，不随请求语言变化（沿用 M5b 最终修复的裁定）。 */
    private function defaultNickname(): string
    {
        return lang('wechat.default_nickname', [], 'zh_CN');
    }

    /**
     * 尽力取 PC 扫码用户的昵称头像：失败不影响登录，按默认昵称注册。
     *
     * @return array{nickname: string, avatar: ?string}
     */
    private function bestEffortProfile(string $accessToken, string $openid): array
    {
        try {
            $info = $this->oauth->userInfo($accessToken, $openid);
        } catch (WechatException $e) {
            Log::warning('获取微信昵称头像失败，按默认昵称注册', ['api' => 'sns/userinfo', 'exception' => $e::class]);
            $info = ['nickname' => null, 'avatar' => null];
        }

        $nickname = trim((string) ($info['nickname'] ?? ''));
        $avatar = $info['avatar'] ?? null;

        return [
            'nickname' => $nickname !== '' ? mb_substr($nickname, 0, self::NICKNAME_MAX_LENGTH) : $this->defaultNickname(),
            'avatar'   => is_string($avatar) && $avatar !== '' && strlen($avatar) <= self::AVATAR_MAX_LENGTH ? $avatar : null,
        ];
    }
}
