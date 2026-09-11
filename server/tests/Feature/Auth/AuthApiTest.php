<?php

declare(strict_types=1);

namespace tests\Feature\Auth;

use support\Cache;
use support\Db;
use tests\Support\ApiTestCase;

final class AuthApiTest extends ApiTestCase
{
    public function test_captcha_returns_key_and_png_data_uri(): void
    {
        $data = $this->get('/adminapi/auth/captcha')->assertOk()->data();

        $this->assertSame(['key', 'image'], array_keys($data), '契约：{key, image}，不是 captcha_key/captcha_image');
        $this->assertStringStartsWith('data:image/png;base64,', $data['image']);
        $this->assertIsString(Cache::get('captcha.' . $data['key']));
    }

    public function test_login_returns_token_and_admin_info_and_writes_login_log(): void
    {
        $admin = $this->actingAsAdmin('super');
        $response = $this->login($admin->username, $admin->password)->assertOk();
        $data = $response->data();

        $this->assertSame(['token', 'admin'], array_keys($data));
        $this->assertArrayNotHasKey('password', $data['admin']);
        $this->assertSame('*', $data['admin']['permissions'][0]);
        $this->assertSame(lang('messages.login_success'), $response->message());
        $row = Db::table('admins')->where('id', $admin->id)->first();
        $this->assertSame(1, (int) $row->login_count);
        $this->assertSame('127.0.0.1', $row->last_login_ip);
        $this->assertSame(1, Db::table('admin_login_logs')->where('admin_id', $admin->id)->where('login_result', 1)->count());
        $this->get('/adminapi/auth/info', [], $data['token'])->assertOk();
    }

    public function test_login_ip_ignores_x_forwarded_for_from_an_untrusted_peer(): void
    {
        $admin = $this->actingAsAdmin();
        [$key, $code] = $this->solveCaptcha();
        $this->post('/adminapi/auth/login', [
            'username'    => $admin->username,
            'password'    => $admin->password,
            'captcha_key' => $key,
            'captcha'     => $code,
        ], null, ['X-Forwarded-For' => '198.51.100.7'])->assertOk();

        $this->assertSame('127.0.0.1', Db::table('admins')->where('id', $admin->id)->value('last_login_ip'));
        $this->assertSame(['127.0.0.1'], Db::table('admin_login_logs')->where('admin_id', $admin->id)->pluck('ip')->all());
    }

    public function test_wrong_password_is_a_business_error_and_is_logged(): void
    {
        $admin = $this->actingAsAdmin();
        $response = $this->login($admin->username, 'wrong-pass');

        $this->assertSame(200, $response->status());
        $this->assertSame(lang('auth.login_failed'), $response->assertCode(400)->message());
        $this->assertSame(1, Db::table('admin_login_logs')->where('admin_id', $admin->id)->where('login_result', 0)->count());
    }

    public function test_disabled_account_cannot_login(): void
    {
        $admin = $this->actingAsAdmin([], ['status' => 0]);

        $this->assertSame(lang('auth.account_disabled'), $this->login($admin->username, $admin->password)->assertCode(400)->message());
    }

    public function test_captcha_is_required_when_enabled(): void
    {
        $admin = $this->actingAsAdmin();
        $response = $this->post('/adminapi/auth/login', ['username' => $admin->username, 'password' => $admin->password]);

        $response->assertCode(422);
        $this->assertArrayHasKey('captcha', $response->data()['errors']);
    }

    public function test_wrong_captcha_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        [$key] = $this->solveCaptcha();
        // 验证码字符集排除了 0，'0000' 不可能碰巧正确
        $response = $this->post('/adminapi/auth/login', ['username' => $admin->username, 'password' => $admin->password, 'captcha_key' => $key, 'captcha' => '0000']);

        $this->assertSame(lang('auth.captcha_invalid'), $response->assertCode(400)->message());
    }

    public function test_captcha_is_optional_when_disabled_by_config(): void
    {
        $admin = $this->actingAsAdmin();
        $this->setConfig('login_captcha', '0');

        $this->post('/adminapi/auth/login', ['username' => $admin->username, 'password' => $admin->password])->assertOk();
    }

    public function test_login_validation_errors_use_lang_messages(): void
    {
        $response = $this->post('/adminapi/auth/login', ['username' => 'ab', 'password' => '123']);

        $response->assertCode(422);
        $this->assertSame(lang('validation.username_length_3_50'), $response->data()['errors']['username']);
    }

    public function test_info_returns_admin_routes_and_permissions(): void
    {
        $admin = $this->actingAsAdmin('super');
        $data = $this->get('/adminapi/auth/info', [], $admin->token)->assertOk()->data();

        $this->assertSame(['admin', 'routes', 'permissions'], array_keys($data));
        $this->assertSame($admin->id, $data['admin']['id']);
        $this->assertSame($data['admin']['permissions'], $data['permissions']);
        $this->assertSame('*', $data['permissions'][0]);
        $this->assertContains('/system', array_column($data['routes'], 'path'));
    }

    public function test_info_without_token_is_http_200_with_code_401(): void
    {
        $response = $this->get('/adminapi/auth/info');

        $this->assertSame(200, $response->status(), '401 只写在 body.code，HTTP 状态仍是 200');
        $response->assertCode(401);
    }

    public function test_refresh_rotates_the_token(): void
    {
        $admin = $this->actingAsAdmin();
        $newToken = $this->post('/adminapi/auth/refresh', [], $admin->token)->assertOk()->data()['token'];

        $this->assertNotSame($admin->token, $newToken);
        $this->get('/adminapi/auth/info', [], $admin->token)->assertCode(401);
        $this->get('/adminapi/auth/info', [], $newToken)->assertOk();
    }

    public function test_logout_revokes_the_token(): void
    {
        $admin = $this->actingAsAdmin();

        $this->post('/adminapi/auth/logout', [], $admin->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $admin->token)->assertCode(401);
    }
}
