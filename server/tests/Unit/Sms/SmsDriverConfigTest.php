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

    /**
     * 修复轮（评审 Important）：TencentSmsDriver::send() 网关拒绝分支原先把整条 SendStatusSet
     * 原样落日志，里面的 PhoneNumber 是未打码的完整 E.164 手机号，等于让同一条日志里的 `mobile`
     * 字段白打了码。修法照 AliyunSmsDriver 的路子——只记 Code/Message，绝不落原始响应体
     * ——并把日志载荷的构造抽成私有静态纯函数 gatewayRejectionLogContext()，这样即使腾讯云 SDK
     * 未安装、send() 整条网络路径不可达，也能用反射直接调用它验证不泄漏手机号。
     */
    public function test_gateway_rejection_log_context_never_leaks_the_raw_phone_number(): void
    {
        $method = new \ReflectionMethod(TencentSmsDriver::class, 'gatewayRejectionLogContext');
        $method->setAccessible(true);

        // 模拟腾讯云 SendStatusSet 的单条记录：PhoneNumber 是未打码的完整号码，SerialNo 也是敏感字段
        $status = [
            'PhoneNumber' => '+8613800138000',
            'SerialNo'    => '5000:abcdefg',
            'Code'        => 'InvalidParameterValue',
            'Message'     => 'sign name not approved',
        ];

        $context = $method->invoke(null, '13800138000', 'SMS_000001', $status);

        $this->assertSame(
            [
                'mobile'   => '138****8000',
                'template' => 'SMS_000001',
                'code'     => 'InvalidParameterValue',
                'message'  => 'sign name not approved',
            ],
            $context
        );
        $this->assertArrayNotHasKey('PhoneNumber', $context);
        $this->assertArrayNotHasKey('response', $context);
        $this->assertStringNotContainsString('+8613800138000', (string) json_encode($context), '日志载荷里不得出现未打码的手机号');
    }

    /** $status 取不到（响应体解析失败）时也不能抛异常，Code/Message 落空字符串。 */
    public function test_gateway_rejection_log_context_tolerates_a_missing_status(): void
    {
        $method = new \ReflectionMethod(TencentSmsDriver::class, 'gatewayRejectionLogContext');
        $method->setAccessible(true);

        $context = $method->invoke(null, '13800138000', 'SMS_000001', null);

        $this->assertSame(
            [
                'mobile'   => '138****8000',
                'template' => 'SMS_000001',
                'code'     => '',
                'message'  => '',
            ],
            $context
        );
    }
}
