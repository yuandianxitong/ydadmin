<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;
use tests\Support\TestResponse;

/** 红线：验证码一次性；登录失败次数与锁定时长按系统配置生效（spec §1.1 差异 1、2）。 */
final class Test12_CaptchaAndLoginLockTest extends ApiTestCase
{
    /** @param array<string, string> $headers */
    private function loginWithoutCaptcha(string $username, string $password, array $headers = []): TestResponse
    {
        return $this->post('/adminapi/auth/login', ['username' => $username, 'password' => $password], null, $headers);
    }

    public function test_captcha_can_only_be_used_once(): void
    {
        $admin = $this->actingAsAdmin();
        [$key, $code] = $this->solveCaptcha();

        $first = $this->post('/adminapi/auth/login', ['username' => $admin->username, 'password' => 'wrong-pass', 'captcha_key' => $key, 'captcha' => $code]);
        $this->assertSame(lang('auth.login_failed'), $first->assertCode(400)->message(), '验证码是对的，失败原因是密码');

        $second = $this->post('/adminapi/auth/login', ['username' => $admin->username, 'password' => $admin->password, 'captcha_key' => $key, 'captcha' => $code]);
        $this->assertSame(lang('auth.captcha_invalid'), $second->assertCode(400)->message(), '同一验证码不能再用');
    }

    public function test_captcha_check_is_case_insensitive(): void
    {
        $admin = $this->actingAsAdmin();
        [$key, $code] = $this->solveCaptcha();

        $this->post('/adminapi/auth/login', ['username' => $admin->username, 'password' => $admin->password, 'captcha_key' => $key, 'captcha' => strtoupper($code)])->assertOk();
    }

    public function test_login_locks_after_configured_failures(): void
    {
        $this->setConfig('login_captcha', '0');
        $this->setConfig('login_max_retry', '2');
        $this->setConfig('login_lock_duration', '1');
        $admin = $this->actingAsAdmin();

        $this->loginWithoutCaptcha($admin->username, 'bad-pass-1')->assertCode(400);
        $this->loginWithoutCaptcha($admin->username, 'bad-pass-2')->assertCode(400);
        $locked = $this->loginWithoutCaptcha($admin->username, $admin->password);

        $this->assertSame(200, $locked->status());
        $locked->assertCode(429);
        $this->assertMatchesRegularExpression('/\d+/', $locked->message(), '提示剩余秒数');

        // 按 IP+用户名计数，别的账号不受影响
        $other = $this->actingAsAdmin();
        $this->loginWithoutCaptcha($other->username, $other->password)->assertOk();
    }

    public function test_lockout_counts_case_variants_of_the_username_together(): void
    {
        $this->setConfig('login_captcha', '0');
        $this->setConfig('login_max_retry', '2');
        $admin = $this->actingAsAdmin();

        $this->loginWithoutCaptcha($admin->username, 'bad-pass-1')->assertCode(400);
        $this->loginWithoutCaptcha(strtoupper($admin->username), 'bad-pass-2')->assertCode(400);
        $locked = $this->loginWithoutCaptcha($admin->username, $admin->password);

        $locked->assertCode(429);
    }

    /**
     * 直连地址不是可信代理（测试环境不配置 TRUSTED_PROXIES，请求来自 127.0.0.1）时 X-Forwarded-For 一律不读：
     * 每次换一个伪造的 X-Forwarded-For 也落在同一个限流 key 上，不能靠它绕过锁定。
     */
    public function test_rotating_x_forwarded_for_does_not_bypass_the_lockout(): void
    {
        $this->setConfig('login_captcha', '0');
        $this->setConfig('login_max_retry', '2');
        $admin = $this->actingAsAdmin();

        $this->loginWithoutCaptcha($admin->username, 'bad-pass-1', ['X-Forwarded-For' => '198.51.100.1'])->assertCode(400);
        $this->loginWithoutCaptcha($admin->username, 'bad-pass-2', ['X-Forwarded-For' => '198.51.100.2'])->assertCode(400);
        $locked = $this->loginWithoutCaptcha($admin->username, $admin->password, ['X-Forwarded-For' => '198.51.100.3']);

        $this->assertSame(200, $locked->status());
        $locked->assertCode(429);
    }

    public function test_success_resets_the_failure_counter(): void
    {
        $this->setConfig('login_captcha', '0');
        $this->setConfig('login_max_retry', '2');
        $admin = $this->actingAsAdmin();

        $this->loginWithoutCaptcha($admin->username, 'bad-pass-1')->assertCode(400);
        $this->loginWithoutCaptcha($admin->username, $admin->password)->assertOk();
        $this->loginWithoutCaptcha($admin->username, 'bad-pass-2')->assertCode(400);
        $this->loginWithoutCaptcha($admin->username, $admin->password)->assertOk();
    }
}
