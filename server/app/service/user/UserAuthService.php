<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\user\UserRepository;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * C 端认证（spec §4.3）：account+password 登录、mobile+code 短信登录、注册、刷新、登出。
 *
 * user scope token 载荷 {user_id, ver}，不带 sid（M4 的会话吊销只为 WS 长连接准备，C 端没有长连接，
 * 计划设计决定 4）；logout 只拉黑当前 jti（TokenManager::blacklist），不调用 revokeSession()。
 *
 * 防计时枚举：account 不存在与密码错误返回同一条消息、跑同一次 bcrypt 成本（照 AdminService::login 先例，
 * DUMMY_PASSWORD_HASH 是同一份值）；禁用账户是独立分支，不在防枚举范围内。
 */
class UserAuthService extends Service
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$cSHA1L2hbXvwZHh/q8T3GO9BunJtH1nllHhcbtYGIwuaqUPJPFrU6';

    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected SmsCodeService $smsCodeService;

    #[Inject]
    protected UserSessionIssuer $userSessionIssuer;

    /** @return array{token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}} */
    public function loginByPassword(string $account, string $password, string $ip): array
    {
        $user = $this->userRepository->findByAccount($account);
        if ($user === null) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            throw new BusinessException(lang('auth.account_login_failed'));
        }
        if ((int) $user['status'] !== 1) {
            throw new BusinessException(lang('auth.account_disabled'));
        }
        if (!password_verify($password, (string) $user['password'])) {
            throw new BusinessException(lang('auth.account_login_failed'));
        }

        return $this->userSessionIssuer->issue((int) $user['id'], $ip);
    }

    /** @return array{token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}} */
    public function loginBySmsCode(string $mobile, string $code, string $ip): array
    {
        $this->smsCodeService->verify($mobile, 'login', $code);
        $user = $this->userRepository->findByAccount($mobile);
        if ($user === null) {
            throw new BusinessException(lang('auth.mobile_not_registered'));
        }
        if ((int) $user['status'] !== 1) {
            throw new BusinessException(lang('auth.account_disabled'));
        }

        return $this->userSessionIssuer->issue((int) $user['id'], $ip);
    }

    /**
     * 注册（spec §4.3；字段与形参名按协调者裁定 2 由 account 改为 mobile）：先校验验证码（用后即删），
     * 再查重，最后建号并直接登录。单条插入不需要事务——没有需要一起回滚的第二步写操作。
     * `password_confirmation` 的比对在控制器的 `confirmed` 校验规则里完成，本方法不重复校验。
     *
     * @return array{token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}}
     */
    public function register(string $mobile, string $password, string $code, string $ip): array
    {
        $this->smsCodeService->verify($mobile, 'register', $code);
        if ($this->userRepository->mobileExists($mobile)) {
            throw new BusinessException(lang('business.mobile_registered'));
        }

        $created = $this->userRepository->create([
            'mobile'   => $mobile,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => '用户' . substr($mobile, -4),
            'status'   => 1,
        ]);

        return $this->userSessionIssuer->issue((int) $created['id'], $ip);
    }

    public function refresh(string $token): string
    {
        $mgr = TokenManager::scope('user');
        $userId = (int) ($mgr->verify($token)['user_id'] ?? 0);

        return $mgr->refresh($token, ['ver' => TokenVersion::current($userId, 'user')]);
    }

    public function logout(string $token): void
    {
        TokenManager::scope('user')->blacklist($token);
    }

    /**
     * C 端「用户整行」收窄（计划设计决定 10），auth/info 用；narrowRow() 与 UserService::narrowRow()
     * （Task 8）是同一份收窄规则，各自维护——见该私有方法的注释。
     *
     * @return array<string, mixed>
     */
    public function getSelfInfo(int $userId): array
    {
        $user = $this->userRepository->find($userId) ?? throw new NotFoundException();

        return $this->narrowRow($user);
    }

    /**
     * 去掉 password（模型 $hidden 已挡，这里显式再去一次不依赖它）、deleted_at、微信四列。
     * 与 `app\service\user\UserService::narrowRow()`（Task 8）是同一份收窄逻辑，各自维护——
     * 改一处要同步改另一处。
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function narrowRow(array $user): array
    {
        unset($user['password'], $user['deleted_at'], $user['openid'], $user['oa_openid'], $user['unionid'], $user['mini_openid']);

        return $user;
    }
}
