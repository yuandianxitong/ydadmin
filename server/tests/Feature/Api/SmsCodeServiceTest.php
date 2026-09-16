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

    /** 除专门测「换 IP 绕不过」那条外，本文件所有用例共用这一个 IP（RFC 5737 文档用地址段）。 */
    private const IP = '203.0.113.10';

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
            "sms_rate:day:{$mobile}",
            "sms_verify_fail:login:{$mobile}",
            "sms_verify_fail:register:{$mobile}",
            'sms_rate:ip:' . md5(self::IP)
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
        $this->service()->send(self::MOBILE, 'register', self::IP);

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
                $this->service()->send(self::MOBILE, $scene, self::IP);
                $this->fail("scene={$scene} 必须被拒绝");
            } catch (ValidationException $e) {
                $this->assertSame(['scene' => lang('validation.sms_scene_invalid')], $e->errors(), $scene);
                $this->assertSame(422, $e->getCode());
            }
        }
        $this->assertSame([], $this->driver->sent, '场景不合法时一条都不该发出去');
        $this->assertSame(0, (int) Redis::exists('sms_rate:minute:' . self::MOBILE), '也不该白白吃掉一次限流额度');
        $this->assertSame(0, (int) Redis::exists('sms_rate:ip:' . md5(self::IP)), 'IP 闸门同样不该被场景校验失败吃掉额度');
    }

    public function test_login_scene_requires_a_registered_mobile_and_register_scene_requires_a_new_one(): void
    {
        try {
            $this->service()->send(self::MOBILE, 'login', self::IP);
            $this->fail('未注册的手机号不能发登录验证码');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_mobile_not_registered'), $e->getMessage());
        }

        $user = $this->actingAsUser(['mobile' => self::MOBILE]);
        $this->assertGreaterThan(0, $user->id);
        $this->forgetSmsKeys(self::MOBILE);

        try {
            $this->service()->send(self::MOBILE, 'register', self::IP);
            $this->fail('已注册的手机号不能发注册验证码');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_mobile_registered'), $e->getMessage());
        }

        $this->forgetSmsKeys(self::MOBILE);
        $this->service()->send(self::MOBILE, 'login', self::IP);
        $this->assertCount(1, $this->driver->sent);
    }

    /**
     * 修复轮第 1 条（安全）：限流必须排在存在性校验之前。同一个未注册号码连续两次探测 login 场景，
     * 第一次报「未注册」在预期之内；第二次如果还报「未注册」，说明探测请求完全没有计入限流配额——
     * 攻击者可以无限速、免费探测任意号码是否已注册。第二次必须直接被手机号限流拦下（code 429）。
     */
    public function test_the_minute_rate_limit_still_counts_a_probe_against_an_unregistered_mobile(): void
    {
        try {
            $this->service()->send(self::MOBILE, 'login', self::IP);
            $this->fail('第一次：未注册的手机号必须报「未注册」');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_mobile_not_registered'), $e->getMessage());
            $this->assertNotSame(429, $e->getCode(), '第一次不该被限流——这一次探测本身才是「消耗配额」的那一次');
        }

        try {
            $this->service()->send(self::MOBILE, 'login', self::IP);
            $this->fail('第二次探测必须被限流拦下，而不是又报一次「未注册」');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode(), '限流判断必须先于存在性校验执行，探测请求才会真的消耗配额');
            $this->assertSame(
                lang('business.sms_rate_limited_minute', ['seconds' => (string) SmsCodeService::MINUTE_WINDOW]),
                $e->getMessage()
            );
        }
        $this->assertSame([], $this->driver->sent, '两次探测全程没有一条真的该发出去');
    }

    public function test_second_request_within_a_minute_is_rate_limited_with_code_429(): void
    {
        $this->service()->send(self::MOBILE, 'register', self::IP);

        try {
            $this->service()->send(self::MOBILE, 'register', self::IP);
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
            $this->service()->send(self::MOBILE, 'register', self::IP);
            $this->fail('超过每日上限必须被限流');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(lang('business.sms_rate_limited_day', ['limit' => (string) SmsCodeService::DAY_LIMIT]), $e->getMessage());
        }
        $this->assertSame([], $this->driver->sent);
    }

    /**
     * 修复轮第 2 条（安全）：`/api/common/sms-code` 是公开路由，只按手机号限流的话换个号就能绕过——
     * 直接代价是运营方的短信费。这里用 IP_LIMIT 个互不相同、事先都不存在的手机号轮流走 register 场景：
     * 前 IP_LIMIT 次都应该正常发出去，第 IP_LIMIT+1 次必须被同一个 IP 的闸门拦下，不管换了哪个新手机号。
     */
    public function test_the_ip_rate_limit_blocks_a_new_mobile_after_the_hourly_cap_is_reached(): void
    {
        $ip = '198.51.100.77'; // 另一段 RFC 5737 文档用地址，避免和本文件其它用例共用的 self::IP 互相影响
        Redis::del('sms_rate:ip:' . md5($ip));
        $base = random_int(1000, 8000);
        $mobileAt = static fn (int $i): string => '138' . str_pad((string) ($base + $i), 8, '0', STR_PAD_LEFT);

        try {
            for ($i = 0; $i < SmsCodeService::IP_LIMIT; ++$i) {
                $this->service()->send($mobileAt($i), 'register', $ip);
            }
            $this->assertCount(SmsCodeService::IP_LIMIT, $this->driver->sent, '前 IP_LIMIT 次、每次都是新手机号，理应全部正常发出');

            try {
                $this->service()->send($mobileAt(SmsCodeService::IP_LIMIT), 'register', $ip);
                $this->fail('第 IP_LIMIT+1 次必须被 IP 限流拦下，即使换了一个全新的手机号');
            } catch (BusinessException $e) {
                $this->assertSame(429, $e->getCode());
                $this->assertSame(
                    lang('business.sms_rate_limited_ip', ['limit' => (string) SmsCodeService::IP_LIMIT]),
                    $e->getMessage()
                );
            }
            $this->assertCount(SmsCodeService::IP_LIMIT, $this->driver->sent, '被 IP 限流拦下的那次不能真发出去');
        } finally {
            Redis::del('sms_rate:ip:' . md5($ip));
            for ($i = 0; $i <= SmsCodeService::IP_LIMIT; ++$i) {
                $this->forgetSmsKeys($mobileAt($i));
            }
        }
    }

    public function test_verify_deletes_the_code_so_it_cannot_be_replayed(): void
    {
        $this->service()->send(self::MOBILE, 'register', self::IP);
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
        $this->service()->send(self::MOBILE, 'register', self::IP);
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
            $this->service()->send(self::MOBILE, 'register', self::IP);
            $this->fail('网关失败必须抛出去');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_send_failed'), $e->getMessage());
        }

        $this->assertSame(0, (int) Redis::exists('sms_code:register:' . self::MOBILE), '用户根本拿不到这串码，留着只是给撞码开窗口');
        $this->assertSame('1', (string) Redis::get('sms_rate:minute:' . self::MOBILE), '限流计数不回退：网关坏掉时更不能让同一个号码每秒重试');
    }

    /**
     * 最终评审第 1 条（Critical）：verify() 失败时不删码本身是有意的（手滑输错一位不该逼用户重等 60 秒），
     * 但没有尝试次数上限时它就是一个 300 秒的撞码窗口——`/api/auth/sms-login` 是公开路由、不校验密码，
     * 6 位码只有 100 万种，无限次尝试即可在 TTL 内穷举出来直接拿到该会员的 user token。
     * 连续错到超过上限时必须把验证码一并作废：之后即使拿着原本正确的那串码也进不去，只能重新发码。
     */
    public function test_too_many_wrong_codes_lock_out_and_kill_the_cached_code(): void
    {
        $this->service()->send(self::MOBILE, 'register', self::IP);
        $code = (string) Redis::get('sms_code:register:' . self::MOBILE);
        $wrong = '000000' === $code ? '111111' : '000000';

        for ($i = 1; $i <= SmsCodeService::VERIFY_FAIL_LIMIT; ++$i) {
            try {
                $this->service()->verify(self::MOBILE, 'register', $wrong);
                $this->fail("第 {$i} 次错码必须被拒绝");
            } catch (ValidationException $e) {
                $this->assertSame(['code' => lang('validation.sms_code_invalid')], $e->errors(), "第 {$i} 次还在上限之内，只是普通的「验证码错误」");
            }
        }
        $this->assertSame($code, (string) Redis::get('sms_code:register:' . self::MOBILE), '上限之内不动缓存里的码');

        try {
            $this->service()->verify(self::MOBILE, 'register', $wrong);
            $this->fail('超过上限的那一次必须被拦下');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode(), '与其它限流一致：HTTP 200 + code 429');
            $this->assertSame(lang('business.sms_verify_too_many_attempts'), $e->getMessage());
        }
        $this->assertSame(0, (int) Redis::exists('sms_code:register:' . self::MOBILE), '撞了这么多次，这串码必须作废');

        try {
            $this->service()->verify(self::MOBILE, 'register', $code);
            $this->fail('作废之后，拿着原本正确的那串码也不能通过——必须重新发码');
        } catch (BusinessException $e) {
            // ValidationException 是 BusinessException 的子类，所以这里靠 code 区分：429 而不是 422
            $this->assertSame(429, $e->getCode(), '码已作废，正确的码也登不进去');
        }
    }

    /**
     * 成功校验必须把失败计数一并清零：上一轮错过的次数不该带进下一轮，
     * 否则「错两次→输对→重新发码」之后只剩下不到一轮的容错，正常用户会被莫名其妙地锁掉。
     */
    public function test_a_successful_verify_clears_the_failure_counter(): void
    {
        $this->service()->send(self::MOBILE, 'register', self::IP);
        $code = (string) Redis::get('sms_code:register:' . self::MOBILE);
        $wrong = '000000' === $code ? '111111' : '000000';

        foreach ([1, 2] as $attempt) {
            try {
                $this->service()->verify(self::MOBILE, 'register', $wrong);
                $this->fail("第 {$attempt} 次错码必须被拒绝");
            } catch (ValidationException $e) {
                $this->assertSame(['code' => lang('validation.sms_code_invalid')], $e->errors());
            }
        }

        $this->service()->verify(self::MOBILE, 'register', $code);
        $this->assertSame(0, (int) Redis::exists('sms_verify_fail:register:' . self::MOBILE), '校验成功即清零');

        // 新一轮：重新发码后，完整的 VERIFY_FAIL_LIMIT 次容错必须都还在（没有累加上一轮那 2 次）
        Redis::del('sms_rate:minute:' . self::MOBILE);
        $this->service()->send(self::MOBILE, 'register', self::IP);
        $fresh = (string) Redis::get('sms_code:register:' . self::MOBILE);

        for ($i = 1; $i <= SmsCodeService::VERIFY_FAIL_LIMIT; ++$i) {
            try {
                $this->service()->verify(self::MOBILE, 'register', '000000' === $fresh ? '111111' : '000000');
                $this->fail("新一轮第 {$i} 次错码必须被拒绝");
            } catch (ValidationException $e) {
                $this->assertSame(['code' => lang('validation.sms_code_invalid')], $e->errors(), "新一轮第 {$i} 次仍应是普通的「验证码错误」，失败计数没有跨轮累加");
            }
        }
        $this->assertSame($fresh, (string) Redis::get('sms_code:register:' . self::MOBILE), '新一轮的码在上限之内仍然有效');
    }

    /**
     * 撞码锁定之后重新发码，必须把失败计数一并清零。
     *
     * 否则用户收到新码、只要再打错一个字，就会立刻又撞上上限、把这条新码也作废，只能再等一轮发送间隔；
     * 最差要熬到计数自然过期（计数的 TTL 从第一次失败起算，重发并不会延长它）。被锁的人往往正是最容易
     * 再手滑一次的人，这个窟窿很窄但真实。
     *
     * 安全上这不算放水：每次 send() 都会覆写验证码，攻击者对**任何一串码**的猜测次数仍被 VERIFY_FAIL_LIMIT
     * 卡死；他的总预算由发送侧决定（同号每分钟 1 次、每天 10 次，同 IP 每小时 20 次），清零只是把容错还给
     * 那串码的合法持有人。
     */
    public function test_resending_a_code_after_a_lockout_restores_the_full_attempt_budget(): void
    {
        $this->service()->send(self::MOBILE, 'register', self::IP);
        $code = (string) Redis::get('sms_code:register:' . self::MOBILE);
        $wrong = '000000' === $code ? '111111' : '000000';

        // 先撞到锁定：上限之内 VERIFY_FAIL_LIMIT 次普通失败，再多一次触发 429 并作废该码
        for ($i = 1; $i <= SmsCodeService::VERIFY_FAIL_LIMIT; ++$i) {
            try {
                $this->service()->verify(self::MOBILE, 'register', $wrong);
                $this->fail("第 {$i} 次错码必须被拒绝");
            } catch (ValidationException $e) {
                $this->assertSame(['code' => lang('validation.sms_code_invalid')], $e->errors());
            }
        }
        try {
            $this->service()->verify(self::MOBILE, 'register', $wrong);
            $this->fail('超过上限的那一次必须被拦下');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode(), '锁定这一下是 HTTP 200 + code 429');
        }

        // 重新发码（发送间隔在测试里手工放行，模拟用户等满 60 秒后再要一条）
        Redis::del('sms_rate:minute:' . self::MOBILE);
        $this->service()->send(self::MOBILE, 'register', self::IP);
        $fresh = (string) Redis::get('sms_code:register:' . self::MOBILE);
        $this->assertNotSame('', $fresh, '重新发码后缓存里应当有一串新码');
        $this->assertSame(0, (int) Redis::exists('sms_verify_fail:register:' . self::MOBILE), '发码即把上一轮的失败计数清零');

        // 关键一步：新码上手就打错一次，应当只是普通的「验证码错误」，而不是再次锁定并烧掉新码
        try {
            $this->service()->verify(self::MOBILE, 'register', '000000' === $fresh ? '111111' : '000000');
            $this->fail('错码必须被拒绝');
        } catch (BusinessException $e) {
            $this->assertInstanceOf(ValidationException::class, $e, '重发之后的第一次手滑只该是普通的校验失败');
            $this->assertNotSame(429, $e->getCode(), '重发之后不该立刻再次锁定');
        }
        $this->assertSame($fresh, (string) Redis::get('sms_code:register:' . self::MOBILE), '新码不该被这一次手滑烧掉');

        // 新码仍然可用，成功后被消费
        $this->service()->verify(self::MOBILE, 'register', $fresh);
        $this->assertSame(0, (int) Redis::exists('sms_code:register:' . self::MOBILE), '校验成功后这串码即作废');
    }
}
