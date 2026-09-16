<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use app\service\user\SmsCodeService;
use support\Redis;
use tests\Support\ApiTestCase;

/**
 * spec §6.2、§7：POST /api/common/sms-code——公开路由、参数校验 422、限流 code 429、
 * 短信配置不全时给固定文案（不回显网关原文）。
 *
 * 这里不测「发送成功」：种子里短信凭据是空的，驱动会在请求网关之前抛「配置不全」——
 * 而填上假凭据就会真的去打阿里云。成功路径由 SmsCodeServiceTest 用假驱动覆盖。
 */
final class SmsCodeApiTest extends ApiTestCase
{
    private const URI = '/api/common/sms-code';

    private const MOBILE = '13900002222';

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetSmsKeys();
    }

    protected function tearDown(): void
    {
        $this->forgetSmsKeys();
        parent::tearDown();
    }

    private function forgetSmsKeys(): void
    {
        Redis::del(
            'sms_code:login:' . self::MOBILE,
            'sms_code:register:' . self::MOBILE,
            'sms_rate:minute:' . self::MOBILE,
            'sms_rate:day:' . self::MOBILE,
            // tests\Support\FakeConnection::getRemoteIp() 对全套件的每个请求都固定返回 127.0.0.1：
            // 这个类里的用例共用同一个 IP 闸门计数，不清掉的话上一条用例的请求次数会带进下一条，
            // 修复轮第 2 条加的 20 次/小时阈值本身够宽松、目前不会被本文件累计的调用次数意外触发，
            // 但显式清零才不依赖「累计次数恰好不到 20」这个脆弱前提。
            'sms_rate:ip:' . md5('127.0.0.1')
        );
    }

    public function test_route_is_public_and_validates_the_mobile(): void
    {
        $response = $this->post(self::URI, ['mobile' => '12345', 'scene' => 'register'])->assertCode(422);

        $this->assertSame(['mobile' => lang('validation.mobile_format')], $response->data()['errors']);

        $missing = $this->post(self::URI, ['scene' => 'register'])->assertCode(422);
        $this->assertSame(['mobile' => lang('validation.mobile_require')], $missing->data()['errors']);
    }

    /**
     * scene 可选，缺省按 login 走（骨架「设计决定」第 12 条）：不传时报的必须是「手机号未注册」——
     * 那说明请求已经走到了登录场景的存在性校验，而不是卡在 scene 的必填校验上。
     * 两个 C 端前端的类型都是 scene?，这条一红就是它们的现有调用被打断了。
     */
    public function test_scene_defaults_to_login_when_omitted(): void
    {
        $response = $this->post(self::URI, ['mobile' => self::MOBILE])->assertCode(400);

        $this->assertSame(lang('business.sms_mobile_not_registered'), $response->message());
    }

    public function test_scene_outside_the_whitelist_is_422_on_the_scene_field(): void
    {
        // 空串也算「显式传了」：sometimes 只放过缺席的字段，不放过传了空值的
        foreach (['reset_password', 'bind_mobile', 'change_mobile', ''] as $scene) {
            $response = $this->post(self::URI, ['mobile' => self::MOBILE, 'scene' => $scene])->assertCode(422);
            $this->assertSame(['scene' => lang('validation.sms_scene_invalid')], $response->data()['errors'], $scene);
        }
        $this->assertSame(0, (int) Redis::exists('sms_rate:minute:' . self::MOBILE));
    }

    public function test_login_scene_rejects_an_unregistered_mobile(): void
    {
        $response = $this->post(self::URI, ['mobile' => self::MOBILE, 'scene' => 'login'])->assertCode(400);

        $this->assertSame(lang('business.sms_mobile_not_registered'), $response->message());
    }

    public function test_register_scene_rejects_a_registered_mobile(): void
    {
        $this->actingAsUser(['mobile' => self::MOBILE]);

        $response = $this->post(self::URI, ['mobile' => self::MOBILE, 'scene' => 'register'])->assertCode(400);

        $this->assertSame(lang('business.sms_mobile_registered'), $response->message());
    }

    /**
     * 种子里 sms_access_key 等都是空的：必须给一句能照做的固定文案，而不是 SDK 的原始报错。
     * 同时钉住「失败也不把验证码留在缓存里」。
     */
    public function test_missing_sms_credentials_return_a_fixed_message(): void
    {
        $this->setConfig('sms_template_register', 'SMS_REGISTER_1');

        $response = $this->post(self::URI, ['mobile' => self::MOBILE, 'scene' => 'register'])->assertCode(400);

        $this->assertSame(lang('business.sms_config_incomplete_aliyun'), $response->message());
        $this->assertStringNotContainsString('AccessKey', $response->data() === [] ? '' : (string) json_encode($response->data()));
        $this->assertSame(0, (int) Redis::exists('sms_code:register:' . self::MOBILE));
    }

    /** 限流在发送之前计数：第一次即使因为配置不全没发出去，第二次也照样 429（否则网关一坏就能被打满）。 */
    public function test_second_request_within_a_minute_returns_code_429(): void
    {
        $this->post(self::URI, ['mobile' => self::MOBILE, 'scene' => 'register']);

        $response = $this->post(self::URI, ['mobile' => self::MOBILE, 'scene' => 'register'])->assertCode(429);

        $this->assertSame(
            lang('business.sms_rate_limited_minute', ['seconds' => (string) SmsCodeService::MINUTE_WINDOW]),
            $response->message()
        );
        $this->assertSame(200, $response->status(), '限流是 HTTP 200 + code 429，不照 TP8 的 HTTP 429');
    }
}
