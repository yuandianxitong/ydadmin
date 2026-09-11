<?php

declare(strict_types=1);

namespace tests\Unit\Helper;

use core\helper\SensitiveDataMasker;
use tests\TestCase;

final class SensitiveDataMaskerTest extends TestCase
{
    public function test_credential_keys_are_masked_recursively_and_case_insensitively(): void
    {
        $input = [
            'username'               => 'alice',
            'Password'               => 'p1',
            'old_password'           => 'p2',
            'new_password'           => 'p3',
            'captcha'                => 'abcd',
            'captcha_key'            => 'k',
            'token'                  => 't',
            'profile'                => ['refresh_token' => 'r', 'nickname' => 'n', 'deep' => ['wechat_app_secret' => 's']],
            'storage_oss_access_key' => 'ak',
            'alipay_private_key'     => 'pk',
            'ids'                    => [1, 2],
        ];

        $this->assertSame([
            'username'               => 'alice',
            'Password'               => '***',
            'old_password'           => '***',
            'new_password'           => '***',
            'captcha'                => '***',
            'captcha_key'            => '***',
            'token'                  => '***',
            'profile'                => ['refresh_token' => '***', 'nickname' => 'n', 'deep' => ['wechat_app_secret' => '***']],
            'storage_oss_access_key' => '***',
            'alipay_private_key'     => '***',
            'ids'                    => [1, 2],
        ], SensitiveDataMasker::mask($input));
    }

    public function test_config_value_is_masked_when_its_config_key_looks_like_a_credential(): void
    {
        $input = ['configs' => [
            ['config_key' => 'site_name', 'config_value' => '元点'],
            ['config_key' => 'storage_cos_secret_key', 'config_value' => 'leak-1'],
            ['config_key' => 'STORAGE_QINIU_ACCESS_KEY', 'config_value' => 'leak-2'],
        ]];

        $this->assertSame(['configs' => [
            ['config_key' => 'site_name', 'config_value' => '元点'],
            ['config_key' => 'storage_cos_secret_key', 'config_value' => '***'],
            ['config_key' => 'STORAGE_QINIU_ACCESS_KEY', 'config_value' => '***'],
        ]], SensitiveDataMasker::mask($input));
    }

    public function test_sensitive_key_detection(): void
    {
        foreach (['password', 'TOKEN', 'captcha', 'app_secret', 'storage_oss_access_key', 'rsa_private_key', 'wechat_pay_api_key', 'mch_key', 'encoding_aes_key', 'access_token', 'password_confirmation'] as $key) {
            $this->assertTrue(SensitiveDataMasker::isSensitiveKey($key), $key);
        }
        foreach (['username', 'config_key', 'config_value', 'nickname', 'site_name', 'public_key', 'status'] as $key) {
            $this->assertFalse(SensitiveDataMasker::isSensitiveKey($key), $key);
        }
    }
}
