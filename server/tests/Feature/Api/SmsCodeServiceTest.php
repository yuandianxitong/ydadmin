<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use app\repository\system\SystemConfigRepository;
use app\repository\user\UserRepository;
use app\service\user\SmsCodeService;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\sms\SmsInterface;
use support\Redis;
use tests\Support\ApiTestCase;

/** 记录型假驱动：只把发出去的内容记下来，一个包都不出网。 */
final class RecordingSmsDriver implements SmsInterface
{
    /** @var list<array{mobile: string, template: string, vars: array<string, string>}> */
    public array $sent = [];

    public ?\Throwable $failWith = null;

    public function send(string $mobile, string $templateId, array $vars): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
        $this->sent[] = ['mobile' => $mobile, 'template' => $templateId, 'vars' => array_map('strval', $vars)];
    }
}

/**
 * spec §7.2：验证码的生成、缓存、限流、校验。发送本身换成假驱动——真发短信要真账号，
 * 这里要断言的是我们自己的那一半逻辑。
 */
final class SmsCodeServiceTest extends ApiTestCase
{
    private const MOBILE = '13900001111';

    private RecordingSmsDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new RecordingSmsDriver();
        $this->forgetSmsKeys(self::MOBILE);
        $this->setConfig('sms_template_login', 'SMS_LOGIN_1');
        $this->setConfig('sms_template_register', 'SMS_REGISTER_1');
    }

    protected function tearDown(): void
    {
        $this->forgetSmsKeys(self::MOBILE);
        parent::tearDown();
    }

    private function forgetSmsKeys(string $mobile): void
    {
        Redis::del(
            "sms_code:login:{$mobile}",
            "sms_code:register:{$mobile}",
            "sms_rate:minute:{$mobile}",
            "sms_rate:day:{$mobile}"
        );
    }

    /**
     * 自己 new 一份服务，把三个 #[Inject] 属性直接填进去：假驱动实现 SmsInterface，另外两个依赖用真的。
     * 不碰容器——容器里的 SmsCodeService 是全套件共用的单例，在它身上换依赖会漏给后面的用例；
     * 也不需要在这里动 SmsInterface 的绑定。
     */
    private function service(): SmsCodeService
    {
        $service = new SmsCodeService();
        $dependencies = [
            'smsDriver'              => $this->driver,
            'userRepository'         => new UserRepository(),
            'systemConfigRepository' => new SystemConfigRepository(),
        ];
        foreach ($dependencies as $name => $value) {
            (new \ReflectionProperty(SmsCodeService::class, $name))->setValue($service, $value);
        }

        return $service;
    }

    public function test_register_scene_caches_a_six_digit_code_for_five_minutes(): void
    {
        $this->service()->send(self::MOBILE, 'register');

        $cached = (string) Redis::get('sms_code:register:' . self::MOBILE);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $cached);
        $ttl = (int) Redis::ttl('sms_code:register:' . self::MOBILE);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(SmsCodeService::CODE_TTL, $ttl);

        $this->assertCount(1, $this->driver->sent);
        $this->assertSame(self::MOBILE, $this->driver->sent[0]['mobile']);
        $this->assertSame('SMS_REGISTER_1', $this->driver->sent[0]['template'], '模板 id 按 scene 取自系统配置');
        $this->assertSame(['code' => $cached], $this->driver->sent[0]['vars'], '发出去的就是缓存里的那一串');
    }

    /** 服务层只认白名单内的显式 scene：「不传按 login」是控制器那一层的入参归一，不在这里兜底。 */
    public function test_scene_whitelist_is_login_and_register_only(): void
    {
        $this->assertSame(['login', 'register'], SmsCodeService::SCENES);

        foreach (['reset_password', 'bind_mobile', 'change_mobile', ''] as $scene) {
            try {
                $this->service()->send(self::MOBILE, $scene);
                $this->fail("scene={$scene} 必须被拒绝");
            } catch (ValidationException $e) {
                $this->assertSame(['scene' => lang('validation.sms_scene_invalid')], $e->errors(), $scene);
                $this->assertSame(422, $e->getCode());
            }
        }
        $this->assertSame([], $this->driver->sent, '场景不合法时一条都不该发出去');
        $this->assertSame(0, (int) Redis::exists('sms_rate:minute:' . self::MOBILE), '也不该白白吃掉一次限流额度');
    }

    public function test_login_scene_requires_a_registered_mobile_and_register_scene_requires_a_new_one(): void
    {
        try {
            $this->service()->send(self::MOBILE, 'login');
            $this->fail('未注册的手机号不能发登录验证码');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_mobile_not_registered'), $e->getMessage());
        }

        $user = $this->actingAsUser(['mobile' => self::MOBILE]);
        $this->assertGreaterThan(0, $user->id);
        $this->forgetSmsKeys(self::MOBILE);

        try {
            $this->service()->send(self::MOBILE, 'register');
            $this->fail('已注册的手机号不能发注册验证码');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_mobile_registered'), $e->getMessage());
        }

        $this->forgetSmsKeys(self::MOBILE);
        $this->service()->send(self::MOBILE, 'login');
        $this->assertCount(1, $this->driver->sent);
    }

    public function test_second_request_within_a_minute_is_rate_limited_with_code_429(): void
    {
        $this->service()->send(self::MOBILE, 'register');

        try {
            $this->service()->send(self::MOBILE, 'register');
            $this->fail('60 秒内第二次必须被限流');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode(), '限流是 HTTP 200 + code 429（本仓库既有先例）');
            $this->assertSame(lang('business.sms_rate_limited_minute', ['seconds' => (string) SmsCodeService::MINUTE_WINDOW]), $e->getMessage());
        }
        $this->assertCount(1, $this->driver->sent, '被限流的那次不能真发出去');
    }

    public function test_eleventh_request_in_a_day_is_rate_limited_with_code_429(): void
    {
        // 直接把日计数顶到上限，不用真发 10 条
        Redis::setEx('sms_rate:day:' . self::MOBILE, SmsCodeService::DAY_WINDOW, (string) SmsCodeService::DAY_LIMIT);

        try {
            $this->service()->send(self::MOBILE, 'register');
            $this->fail('超过每日上限必须被限流');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(lang('business.sms_rate_limited_day', ['limit' => (string) SmsCodeService::DAY_LIMIT]), $e->getMessage());
        }
        $this->assertSame([], $this->driver->sent);
    }

    public function test_verify_deletes_the_code_so_it_cannot_be_replayed(): void
    {
        $this->service()->send(self::MOBILE, 'register');
        $code = (string) Redis::get('sms_code:register:' . self::MOBILE);

        $this->service()->verify(self::MOBILE, 'register', $code);

        $this->assertSame(0, (int) Redis::exists('sms_code:register:' . self::MOBILE), '校验通过立即删除（防重放）');

        try {
            $this->service()->verify(self::MOBILE, 'register', $code);
            $this->fail('同一个验证码不能用第二次');
        } catch (ValidationException $e) {
            $this->assertSame(['code' => lang('validation.sms_code_invalid')], $e->errors());
        }
    }

    public function test_a_wrong_code_fails_without_consuming_the_cached_one(): void
    {
        $this->service()->send(self::MOBILE, 'register');
        $code = (string) Redis::get('sms_code:register:' . self::MOBILE);

        try {
            $this->service()->verify(self::MOBILE, 'register', '000000' === $code ? '111111' : '000000');
            $this->fail('错误的验证码必须被拒绝');
        } catch (ValidationException $e) {
            $this->assertSame(['code' => lang('validation.sms_code_invalid')], $e->errors());
        }

        // 手滑输错一位不该逼用户重新等 60 秒
        $this->assertSame($code, (string) Redis::get('sms_code:register:' . self::MOBILE));
        $this->service()->verify(self::MOBILE, 'register', $code);
    }

    public function test_a_failed_send_drops_the_cached_code_but_keeps_the_rate_counter(): void
    {
        $this->driver->failWith = new BusinessException(lang('business.sms_send_failed'));

        try {
            $this->service()->send(self::MOBILE, 'register');
            $this->fail('网关失败必须抛出去');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_send_failed'), $e->getMessage());
        }

        $this->assertSame(0, (int) Redis::exists('sms_code:register:' . self::MOBILE), '用户根本拿不到这串码，留着只是给撞码开窗口');
        $this->assertSame('1', (string) Redis::get('sms_rate:minute:' . self::MOBILE), '限流计数不回退：网关坏掉时更不能让同一个号码每秒重试');
    }
}
