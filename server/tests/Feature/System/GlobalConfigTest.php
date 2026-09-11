<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\SystemConfigRepository;
use app\service\system\SystemConfigService;
use support\Db;
use tests\Support\ApiTestCase;

final class GlobalConfigTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        (new SystemConfigRepository())->forgetCache(); // 夹具插入的配置行已删，缓存也要清
    }

    public function test_returns_flat_converted_map_without_credentials(): void
    {
        $now = date('Y-m-d H:i:s');
        $keys = ['storage_oss_access_key', 'storage_oss_access_secret', 'smtp_pass', 'pay_wechat_api_v3_key', 'wechat_official_aes_key', 'third_party_token', 'pay_alipay_private_key'];
        foreach ($keys as $i => $key) {
            $this->track('system_configs', (int) Db::table('system_configs')->insertGetId([
                'config_key' => $key, 'config_value' => "SECRET-{$i}", 'config_group' => 'global_test',
                'config_type' => 'string', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
        (new SystemConfigRepository())->forgetCache();

        $admin = $this->actingAsAdmin();
        $data = $this->get('/adminapi/system/config/global', [], $admin->token)->assertOk()->data();

        $this->assertSame('元点Admin', $data['site_name']);
        $this->assertTrue($data['login_captcha']);
        $this->assertSame(5, $data['login_max_retry']);
        $this->assertArrayHasKey('site_favicon', $data);
        foreach (array_keys($data) as $key) {
            $this->assertFalse(SystemConfigService::isSensitiveKey($key), "config/global 泄露了凭据类键 {$key}");
        }
        $this->assertStringNotContainsString('SECRET-', (string) json_encode($data));
    }

    public function test_requires_login(): void
    {
        $this->get('/adminapi/system/config/global')->assertCode(401);
    }

    public function test_sensitive_key_rule(): void
    {
        foreach (['smtp_pass', 'sms_access_secret', 'storage_cos_secret_key', 'storage_qiniu_access_key', 'pay_wechat_api_v3_key', 'pay_wechat_api_key', 'wechat_mini_aes_key', 'API_TOKEN', 'password_min_length'] as $key) {
            $this->assertTrue(SystemConfigService::isSensitiveKey($key), $key);
        }
        foreach (['site_name', 'site_keywords', 'login_captcha', 'storage_oss_domain', 'storage_driver', 'login_max_retry'] as $key) {
            $this->assertFalse(SystemConfigService::isSensitiveKey($key), $key);
        }
    }
}
