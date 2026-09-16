<?php

declare(strict_types=1);

namespace tests\Unit\Sms;

use core\exception\BusinessException;
use core\sms\driver\AliyunSmsDriver;
use core\sms\driver\TencentSmsDriver;
use core\sms\SmsInterface;
use tests\TestCase;

/**
 * 两个短信驱动的离线断言：凭据校验、模板校验、腾讯云 SDK 缺失的提示。全程不发网络请求——
 * 真实发送需要真账号与已审核的签名/模板，留给运维在配置页填完后手工验证（与 M1c 云存储驱动同一分界）。
 */
final class SmsDriverConfigTest extends TestCase
{
    /** @return array{access_key: string, access_secret: string, sign_name: string, sdk_app_id: string} */
    private function config(array $overrides = []): array
    {
        return array_merge([
            'access_key'    => 'dummy-ak',
            'access_secret' => 'dummy-sk',
            'sign_name'     => '元点科技',
            'sdk_app_id'    => '1400000000',
        ], $overrides);
    }

    private function assertIncomplete(callable $construct, string $langKey, string $missingKey): void
    {
        try {
            $construct();
            $this->fail("缺少 {$missingKey} 时必须抛 BusinessException，不能构造成功");
        } catch (BusinessException $e) {
            $this->assertSame(lang($langKey), $e->getMessage(), $missingKey);
            $this->assertStringNotContainsString('dummy-sk', $e->getMessage(), '异常消息里不得出现凭据');
        }
    }

    public function test_aliyun_requires_ak_sk_and_sign_name(): void
    {
        foreach (['access_key', 'access_secret', 'sign_name'] as $key) {
            $this->assertIncomplete(fn () => new AliyunSmsDriver($this->config([$key => ''])), 'business.sms_config_incomplete_aliyun', $key);
        }
        // sdk_app_id 是腾讯云专用的，阿里云不填也能构造
        $this->assertInstanceOf(SmsInterface::class, new AliyunSmsDriver($this->config(['sdk_app_id' => ''])));
    }

    public function test_tencent_requires_ak_sk_sign_name_and_sdk_app_id(): void
    {
        foreach (['access_key', 'access_secret', 'sign_name', 'sdk_app_id'] as $key) {
            $this->assertIncomplete(fn () => new TencentSmsDriver($this->config([$key => ''])), 'business.sms_config_incomplete_tencent', $key);
        }
    }

    /** 管理端是文本输入框，纯空格必须当「没填」处理（与云存储驱动同一处理）。 */
    public function test_whitespace_only_required_value_is_treated_as_incomplete(): void
    {
        $this->assertIncomplete(fn () => new AliyunSmsDriver($this->config(['sign_name' => '   '])), 'business.sms_config_incomplete_aliyun', 'sign_name (aliyun)');
        $this->assertIncomplete(fn () => new TencentSmsDriver($this->config(['sdk_app_id' => "\t"])), 'business.sms_config_incomplete_tencent', 'sdk_app_id (tencent)');
    }

    /**
     * 腾讯云 SDK 默认不在依赖树里（计划「设计决定」第 8 条）：配置填全了也要给一条能直接照做的错误，
     * 而不是 "Class not found" 这种 500。判定顺序是「先配置、后 SDK」——配置本来就没填全时，
     * 先说「去填配置」比先说「去装 SDK」更接近运维真正要做的事。
     */
    public function test_tencent_reports_a_missing_sdk_when_fully_configured(): void
    {
        if (class_exists('TencentCloud\\Sms\\V20210111\\SmsClient')) {
            $this->markTestSkipped('已安装 tencentcloud/sms：本条与 SmsSdkAvailabilityTest 的决策守卫一起改');
        }

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(lang('business.sms_tencent_sdk_missing'));
        new TencentSmsDriver($this->config());
    }

    /** 模板没配时在发请求之前就抛，不把空 TemplateCode 送去网关。 */
    public function test_aliyun_rejects_an_empty_template_before_touching_the_network(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(lang('business.sms_template_missing'));
        (new AliyunSmsDriver($this->config()))->send('13800138000', '  ', ['code' => '123456']);
    }
}
