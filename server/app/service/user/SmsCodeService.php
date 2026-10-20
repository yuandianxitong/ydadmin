<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\system\SystemConfigRepository;
use app\repository\user\UserRepository;
use app\service\system\SystemConfigService;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\sms\SmsInterface;
use DI\Attribute\Inject;
use support\Redis;

/**
 * 短信验证码（spec §7.2）。生成、缓存、校验、限流都是业务，不进 core/sms——core 只管把一条短信交给网关。
 *
 * 键：验证码 `sms_code:{scene}:{mobile}`（TTL 300 秒）；校验失败计数 `sms_verify_fail:{scene}:{mobile}`
 * （TTL 同验证码，见 verify()）；限流 `sms_rate:minute:{mobile}`（1 次 / 60 秒）、
 * `sms_rate:day:{mobile}`（10 次 / 86400 秒）与 `sms_rate:ip:` . md5($ip)（20 次 / 3600 秒，按 IP，
 * 修复轮第 2 条），超出一律 BusinessException code 429（HTTP 200 + code 429，与 LoginRateLimitMiddleware、
 * WsTicketService 同一先例，不照 TP8 的 HTTP 429）。计数的 INCR 与 EXPIRE 走一段 Lua 原子执行：分成两条命令时，
 * worker 在两条之间退出会留下不过期的计数，那个手机号/IP 就再也发不出验证码了。
 *
 * IP 闸门单独存在的理由：`/api/common/sms-code` 是公开路由，只按手机号限流的话，换个号就能绕过——
 * 直接代价是运营方的短信费。20 次/小时是有意放宽的阈值：企业 NAT 出口大量真实用户共用一个公网 IP，
 * 定得太严会连带误伤这些正常用户；20 次仍然把单 IP 轰炸的成本压得很低。
 *
 * IP 与每日配额按尝试计数，防止号码探测和网关故障期间的无限重试。
 * 每分钟间隔只在实际发送成功后保留；失败按令牌释放本次预约，允许用户立即修正后重试。
 *
 * 容器单例，无状态（请求态一律经参数传递）。
 */
class SmsCodeService extends Service
{
    /** M5a 只开这两个场景（spec §7.2）：reset_password / bind_mobile / change_mobile 等 M6 接消息模板时再扩。 */
    public const SCENES = ['login', 'register'];

    public const CODE_TTL = 300;

    public const MINUTE_LIMIT = 1;

    public const MINUTE_WINDOW = 60;

    public const DAY_LIMIT = 10;

    public const DAY_WINDOW = 86400;

    /** 校验侧的尝试次数上限（最终评审第 1 条）：理由见 verify()。 */
    public const VERIFY_FAIL_LIMIT = 5;

    /** 按 IP 的闸门（修复轮第 2 条）：理由见类注释。 */
    public const IP_LIMIT = 20;

    public const IP_WINDOW = 3600;

    /** 计数 +1 并保证带过期时间，一段 Lua 原子执行（与 LoginRateLimitMiddleware 同一写法）。 */
    private const INCR_WITH_TTL = "local n = redis.call('INCR', KEYS[1]) if n == 1 or redis.call('TTL', KEYS[1]) == -1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end return n";

    /**
     * 比对、删除、失败计数放在同一段 Lua 里。先 GET 再 DEL 时，两个 worker 会同时看到验证码还在，各成功一次。
     * 命中返回 1；未命中或验证码不存在返回负的失败次数。
     */
    private const VERIFY_AND_CONSUME = <<<'LUA'
local stored = redis.call('GET', KEYS[1])
if stored and stored == ARGV[1] then
  redis.call('DEL', KEYS[1])
  redis.call('DEL', KEYS[2])
  return 1
end
local fails = redis.call('INCR', KEYS[2])
if fails == 1 then
  redis.call('EXPIRE', KEYS[2], tonumber(ARGV[2]))
end
if stored and fails > tonumber(ARGV[3]) then
  redis.call('DEL', KEYS[1])
end
return -fails
LUA;

    /** @var array<string, string> scene => 模板 id 的配置键（spec §7.1） */
    private const TEMPLATE_KEYS = [
        'login'    => 'sms_template_login',
        'register' => 'sms_template_register',
    ];

    /** 容器共享无状态 SmsManager，发送时才读取配置快照并构造驱动。 */
    #[Inject]
    protected SmsInterface $smsDriver;

    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected SystemConfigRepository $systemConfigRepository;

    #[Inject]
    protected SystemConfigService $systemConfigService;

    /**
     * @param string $ip 由控制器经 core\http\ClientIp::resolve() 取得，服务层不接 Request（分层约定）
     *
     * @throws ValidationException scene 不在白名单（errors.scene）
     * @throws BusinessException   限流（IP 或手机号，code 429）、手机号与场景不符、短信配置不全或网关失败
     */
    public function send(string $mobile, string $scene, string $ip): void
    {
        $this->assertScene($scene);
        if ($scene === 'register' && !$this->systemConfigService->isSecuritySwitchOn('user_register')) {
            throw new BusinessException(lang('business.register_closed'));
        }
        $this->assertWithinIpRateLimit($ip);
        $this->assertMobileFitsScene($mobile, $scene);
        $template = $this->templateId($scene);
        $token = bin2hex(random_bytes(16));
        $reserved = false;
        try {
            $this->assertWithinRateLimit($mobile, $token);
            $reserved = true;
            $perDay = (int) Redis::eval(self::INCR_WITH_TTL, 1, "sms_rate:day:{$mobile}", self::DAY_WINDOW);
            if ($perDay > self::DAY_LIMIT) {
                throw new BusinessException(lang('business.sms_rate_limited_day', ['limit' => (string) self::DAY_LIMIT]), 429);
            }
            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
            $this->smsDriver->send($mobile, $template, ['code' => $code]);
            Redis::setEx(self::codeKey($mobile, $scene), self::CODE_TTL, $code);
            Redis::del(self::verifyFailKey($mobile, $scene));
        } catch (\Throwable $e) {
            if ($reserved) {
                // 只释放自己的预约，不能删除耗时发送期间新请求建立的间隔。
                Redis::eval("if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) end return 0", 1, "sms_rate:minute:{$mobile}", $token);
            }
            throw $e;
        }
    }

    /**
     * 校验通过后立即删除验证码键，防重放（spec §7.2）。
     * 校验失败**不**删：手滑输错一位不该逼用户重新等 60 秒。
     *
     * 但「失败不删」单独存在就是一个 300 秒的撞码窗口（最终评审第 1 条）：`/api/auth/sms-login` 是公开
     * 路由、没有任何速率约束、而且不校验密码，6 位码只有 100 万种——对一个已注册号码发一次码，就能在
     * TTL 内高速穷举出来，直接拿到该会员的 user token。发送侧的三道限流一概管不到这里，那是另一侧。
     * 所以按 `sms_verify_fail:{scene}:{mobile}` 计失败次数，超过 VERIFY_FAIL_LIMIT 次就把验证码一并
     * 作废：到这个次数已经不是「手滑输错一位」了，必须重新发码。计数 TTL 与验证码一致（300 秒），
     * 所以即便误伤，代价上限也只是等这一轮码自然过期。
     * 校验成功时把计数一起删掉：一轮里错两次再输对，不该把这两次带进下一轮。
     *
     * @throws ValidationException errors.code：验证码错误或已过期
     * @throws BusinessException   连续输错超过上限（code 429），验证码同时作废
     */
    public function verify(string $mobile, string $scene, string $code): void
    {
        $this->assertScene($scene);
        $connection = Redis::connection();
        $arguments = [
            self::codeKey($mobile, $scene),
            self::verifyFailKey($mobile, $scene),
            $code,
            (string) self::CODE_TTL,
            (string) self::VERIFY_FAIL_LIMIT,
        ];
        $result = $connection instanceof \Illuminate\Redis\Connections\PhpRedisConnection
            ? $connection->eval(self::VERIFY_AND_CONSUME, 2, ...$arguments)
            : $connection->command('eval', [self::VERIFY_AND_CONSUME, 2, ...$arguments]);
        if ((int) $result === 1) {
            return;
        }
        if ((int) $result < -self::VERIFY_FAIL_LIMIT) {
            throw new BusinessException(lang('business.sms_verify_too_many_attempts'), 429);
        }

        throw new ValidationException(['code' => lang('validation.sms_code_invalid')]);
    }

    private function assertScene(string $scene): void
    {
        if (!in_array($scene, self::SCENES, true)) {
            throw new ValidationException(['scene' => lang('validation.sms_scene_invalid')]);
        }
    }

    /** login 要求手机号已注册、register 要求未注册（spec §7.2 第 3 步）。 */
    private function assertMobileFitsScene(string $mobile, string $scene): void
    {
        $exists = $this->userRepository->mobileExists($mobile);
        if ($scene === 'login' && !$exists) {
            throw new BusinessException(lang('business.sms_mobile_not_registered'));
        }
        if ($scene === 'register' && $exists) {
            throw new BusinessException(lang('business.sms_mobile_registered'));
        }
    }

    private function assertWithinRateLimit(string $mobile, string $token): void
    {
        $script = "if redis.call('EXISTS', KEYS[1]) == 1 then return 0 end redis.call('SET', KEYS[1], ARGV[1], 'EX', ARGV[2]) return 1";
        $arguments = ["sms_rate:minute:{$mobile}", $token, (string) self::MINUTE_WINDOW];
        $connection = Redis::connection();
        $reserved = (int) ($connection instanceof \Illuminate\Redis\Connections\PhpRedisConnection
            ? $connection->eval($script, 1, ...$arguments)
            : $connection->command('eval', [$script, 1, ...$arguments]));
        if ($reserved !== 1) {
            throw new BusinessException(lang('business.sms_rate_limited_minute', ['seconds' => (string) self::MINUTE_WINDOW]), 429);
        }
    }

    /**
     * 按 IP 的闸门（修复轮第 2 条）：`/api/common/sms-code` 是公开路由，只按手机号限流的话换个号就能
     * 绕过——直接代价是运营方的短信费。20 次/小时是有意放宽的阈值：企业 NAT 出口大量真实用户共用一个
     * 公网 IP，定得太严会连带误伤这些正常用户；20 次仍然把单 IP 轰炸的成本压得很低。
     */
    private function assertWithinIpRateLimit(string $ip): void
    {
        $perHour = (int) Redis::eval(self::INCR_WITH_TTL, 1, 'sms_rate:ip:' . md5($ip), self::IP_WINDOW);
        if ($perHour > self::IP_LIMIT) {
            throw new BusinessException(lang('business.sms_rate_limited_ip', ['limit' => (string) self::IP_LIMIT]), 429);
        }
    }

    /** 模板 id 按 scene 取自系统配置；没配就按「配置不全」处理，不把空模板送去网关。 */
    private function templateId(string $scene): string
    {
        $template = trim((string) $this->systemConfigRepository->getConfigValue(self::TEMPLATE_KEYS[$scene] ?? '', ''));
        if ($template === '') {
            throw new BusinessException(lang('business.sms_template_missing'));
        }

        return $template;
    }

    private static function codeKey(string $mobile, string $scene): string
    {
        return "sms_code:{$scene}:{$mobile}";
    }

    /** 校验失败计数（最终评审第 1 条）：与验证码同 scene、同手机号、同 TTL。 */
    private static function verifyFailKey(string $mobile, string $scene): string
    {
        return "sms_verify_fail:{$scene}:{$mobile}";
    }
}
