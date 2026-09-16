<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\system\SystemConfigRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\sms\SmsInterface;
use DI\Attribute\Inject;
use support\Container;
use support\Redis;

/**
 * 短信验证码（spec §7.2）。生成、缓存、校验、限流都是业务，不进 core/sms——core 只管把一条短信交给网关。
 *
 * 键：验证码 `sms_code:{scene}:{mobile}`（TTL 300 秒）；限流 `sms_rate:minute:{mobile}`（1 次 / 60 秒）
 * 与 `sms_rate:day:{mobile}`（10 次 / 86400 秒），超出一律 BusinessException code 429
 * （HTTP 200 + code 429，与 LoginRateLimitMiddleware、WsTicketService 同一先例，不照 TP8 的 HTTP 429）。
 * 计数的 INCR 与 EXPIRE 走一段 Lua 原子执行：分成两条命令时，worker 在两条之间退出会留下不过期的计数，
 * 那个手机号就再也发不出验证码了。
 *
 * 限流计数在**发送之前**记：网关坏掉、配置没填全的时候更不能让同一个号码每秒重试。代价是配置没配好时，
 * 用户要等 60 秒才能再试一次——这正确，因为重试也不会成功。
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

    /** 计数 +1 并保证带过期时间，一段 Lua 原子执行（与 LoginRateLimitMiddleware 同一写法）。 */
    private const INCR_WITH_TTL = "local n = redis.call('INCR', KEYS[1]) if n == 1 or redis.call('TTL', KEYS[1]) == -1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end return n";

    /** @var array<string, string> scene => 模板 id 的配置键（spec §7.1） */
    private const TEMPLATE_KEYS = [
        'login'    => 'sms_template_login',
        'register' => 'sms_template_register',
    ];

    /**
     * 容器把这个接口解析成 SmsManager 按 sms_driver 选出的驱动（config/container.php）。
     *
     * 故意不标 #[Inject]：php-di 的属性注入在**对象构造那一刻**立刻发生。这个属性一旦标了
     * #[Inject]，只要短信凭据不全（AliyunSmsDriver 构造函数按 spec §7.3 在那时就抛
     * BusinessException），构造 SmsCodeService——乃至只是构造依赖它的 CommonController——
     * 就会先于 assertScene()/assertMobileFitsScene()/限流判断炸掉：连格式校验、场景白名单
     * 这些跟短信网关毫无关系的请求也会被牵连，一律先报「配置不全」。
     * 改成 driver() 懒解析：只有真正跑到 send() 内部要发信这一步才会去问容器，
     * 这时前面的校验早已经通过。测试（SmsCodeServiceTest）直接用反射把假驱动塞进这个属性，
     * isset() 为真就不会再碰容器，也不需要给测试另外准备一个假 SmsManager。
     */
    protected SmsInterface $smsDriver;

    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected SystemConfigRepository $systemConfigRepository;

    /**
     * @throws ValidationException scene 不在白名单（errors.scene）
     * @throws BusinessException   手机号与场景不符、限流（code 429）、短信配置不全或网关失败
     */
    public function send(string $mobile, string $scene): void
    {
        $this->assertScene($scene);
        $this->assertMobileFitsScene($mobile, $scene);
        $this->assertWithinRateLimit($mobile);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Redis::setEx(self::codeKey($mobile, $scene), self::CODE_TTL, $code);

        try {
            $this->driver()->send($mobile, $this->templateId($scene), ['code' => $code]);
        } catch (\Throwable $e) {
            // 发不出去就别把验证码留在缓存里：用户根本拿不到它，留着只是白白开一个 5 分钟的撞码窗口。
            // 限流计数**不**回退，理由见类注释。
            Redis::del(self::codeKey($mobile, $scene));

            throw $e;
        }
    }

    /**
     * 校验通过后立即删除验证码键，防重放（spec §7.2）。
     * 校验失败**不**删：手滑输错一位不该逼用户重新等 60 秒——重放风险由一次性删除与 5 分钟 TTL 兜住。
     *
     * @throws ValidationException errors.code：验证码错误或已过期
     */
    public function verify(string $mobile, string $scene, string $code): void
    {
        $this->assertScene($scene);
        $cached = Redis::get(self::codeKey($mobile, $scene));
        if (!is_string($cached) || $cached === '' || !hash_equals($cached, $code)) {
            throw new ValidationException(['code' => lang('validation.sms_code_invalid')]);
        }
        Redis::del(self::codeKey($mobile, $scene));
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

    private function assertWithinRateLimit(string $mobile): void
    {
        $perMinute = (int) Redis::eval(self::INCR_WITH_TTL, 1, "sms_rate:minute:{$mobile}", self::MINUTE_WINDOW);
        if ($perMinute > self::MINUTE_LIMIT) {
            throw new BusinessException(lang('business.sms_rate_limited_minute', ['seconds' => (string) self::MINUTE_WINDOW]), 429);
        }

        $perDay = (int) Redis::eval(self::INCR_WITH_TTL, 1, "sms_rate:day:{$mobile}", self::DAY_WINDOW);
        if ($perDay > self::DAY_LIMIT) {
            throw new BusinessException(lang('business.sms_rate_limited_day', ['limit' => (string) self::DAY_LIMIT]), 429);
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

    /**
     * 懒解析短信驱动（见 $smsDriver 属性上的注释）：第一次真正调用才向容器要一个，之后缓存在
     * 属性里（同一次请求内 SmsCodeService 是容器单例，但下一次请求想要「刚换的服务商」仍然生效——
     * 容器侧的 core\sms\SmsInterface 绑定本身就是每个 worker 进程解析一次并共享，
     * 这条代价已经写在 config/container.php 的绑定注释里，这里只是把同一个共享实例存下来复用，
     * 不是又加了一层缓存）。
     */
    private function driver(): SmsInterface
    {
        if (!isset($this->smsDriver)) {
            $this->smsDriver = Container::get(SmsInterface::class);
        }

        return $this->smsDriver;
    }
}
