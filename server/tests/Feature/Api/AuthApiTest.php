<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;

/**
 * spec §6.2、§4.3：C 端认证。login(account+password，account 缺失时收 mobile 顶替——
 * pc/uniapp 两端字段名本就不一致，协调者裁定 1)、register(mobile+password+password_confirmation+code，
 * 协调者裁定 2)、sms-login(mobile+code) 公开；refresh-token/info/logout 挂 ApiAuthMiddleware。
 * 验证码走真实 Redis 键（core\sms 与 SmsCodeService 是 Task 4/5 的产物，这里只借用它们的缓存键格式）。
 */
final class AuthApiTest extends ApiTestCase
{
    private function mobile(): string
    {
        return '138' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    protected function tearDown(): void
    {
        // SmsCodeService 用后即删；测试提前失败时兜底清一遍，避免污染下一条用例
        foreach (['register', 'login'] as $scene) {
            Redis::del("sms_code:{$scene}:*");
        }
        parent::tearDown();
    }

    public function test_login_succeeds_and_returns_token_with_four_field_user_info(): void
    {
        $user = $this->actingAsUser();

        $response = $this->post('/api/auth/login', ['account' => $user->mobile, 'password' => $user->password])->assertOk();
        $data = $response->data();

        $this->assertSame(['token', 'user_info'], array_keys($data));
        $this->assertSame(['id', 'nickname', 'avatar', 'mobile'], array_keys($data['user_info']));
        $this->assertSame($user->id, $data['user_info']['id']);
        $this->assertSame($user->mobile, $data['user_info']['mobile']);
        $this->assertArrayNotHasKey('password', $data['user_info']);
        $this->assertSame(1, (int) Db::table('users')->where('id', $user->id)->value('login_count'));
    }

    public function test_login_fails_for_unknown_account_or_wrong_password_with_the_same_message(): void
    {
        $user = $this->actingAsUser();

        $wrong = $this->post('/api/auth/login', ['account' => $user->mobile, 'password' => 'WrongPass1'])->assertCode(400);
        $unknown = $this->post('/api/auth/login', ['account' => $this->mobile(), 'password' => 'WrongPass1'])->assertCode(400);

        $this->assertSame(lang('auth.account_login_failed'), $wrong->message());
        $this->assertSame(lang('auth.account_login_failed'), $unknown->message());
    }

    public function test_login_rejects_disabled_account(): void
    {
        $user = $this->actingAsUser(['status' => 0]);

        $response = $this->post('/api/auth/login', ['account' => $user->mobile, 'password' => $user->password])->assertCode(400);
        $this->assertSame(lang('auth.account_disabled'), $response->message());
    }

    /** 协调者裁定 1：pc 发 account、uniapp 发 mobile，服务端以 account 为准、缺失时收 mobile 顶替。 */
    public function test_login_accepts_mobile_as_a_fallback_field_name_for_account(): void
    {
        $user = $this->actingAsUser();

        $response = $this->post('/api/auth/login', ['mobile' => $user->mobile, 'password' => $user->password])->assertOk();

        $this->assertSame($user->id, $response->data()['user_info']['id']);
    }

    public function test_login_rejects_when_both_account_and_mobile_are_missing(): void
    {
        $response = $this->post('/api/auth/login', ['password' => 'Passw0rd!'])->assertCode(422);

        $this->assertSame(lang('validation.account_require'), $response->data()['errors']['account']);
    }

    public function test_register_creates_the_account_and_logs_in(): void
    {
        $mobile = $this->mobile();
        Redis::set("sms_code:register:{$mobile}", '123456', 'EX', 300);

        $response = $this->post('/api/auth/register', [
            'mobile'                => $mobile,
            'password'              => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'code'                  => '123456',
        ])->assertOk();
        $data = $response->data();
        $this->track('users', (int) $data['user_info']['id']);

        $this->assertSame('用户' . substr($mobile, -4), $data['user_info']['nickname']);
        $this->assertSame(1, (int) Db::table('users')->where('id', $data['user_info']['id'])->value('login_count'));
        $this->assertNull(Redis::get("sms_code:register:{$mobile}"), '验证码用后即删');
    }

    /** 协调者裁定 2：register 字段是 mobile+code+password+password_confirmation。 */
    public function test_register_rejects_a_mismatched_password_confirmation(): void
    {
        $mobile = $this->mobile();
        Redis::set("sms_code:register:{$mobile}", '123456', 'EX', 300);

        $response = $this->post('/api/auth/register', [
            'mobile'                => $mobile,
            'password'              => 'Passw0rd!',
            'password_confirmation' => 'Different1!',
            'code'                  => '123456',
        ])->assertCode(422);

        $this->assertSame(lang('validation.password_confirmation_mismatch'), $response->data()['errors']['password']);
        $this->assertSame(0, Db::table('users')->where('mobile', $mobile)->count(), '两次密码不一致时不建号');
    }

    public function test_register_rejects_a_mobile_that_is_already_registered(): void
    {
        $user = $this->actingAsUser();
        Redis::set("sms_code:register:{$user->mobile}", '123456', 'EX', 300);

        $response = $this->post('/api/auth/register', [
            'mobile'                => $user->mobile,
            'password'              => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'code'                  => '123456',
        ])->assertCode(400);
        $this->assertSame(lang('business.mobile_registered'), $response->message());
    }

    public function test_register_rejects_a_wrong_code(): void
    {
        $mobile = $this->mobile();
        Redis::set("sms_code:register:{$mobile}", '654321', 'EX', 300);

        $this->post('/api/auth/register', [
            'mobile'                => $mobile,
            'password'              => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'code'                  => '000000',
        ])->assertCode(422);
        $this->assertSame(0, Db::table('users')->where('mobile', $mobile)->count(), '校验码错误时不建号');
    }

    public function test_sms_login_rejects_an_unregistered_mobile_without_auto_registering(): void
    {
        $mobile = $this->mobile();
        Redis::set("sms_code:login:{$mobile}", '123456', 'EX', 300);

        $response = $this->post('/api/auth/sms-login', ['mobile' => $mobile, 'code' => '123456'])->assertCode(400);

        $this->assertSame(lang('auth.mobile_not_registered'), $response->message());
        $this->assertSame(0, Db::table('users')->where('mobile', $mobile)->count());
    }

    public function test_sms_login_succeeds_for_a_registered_mobile(): void
    {
        $user = $this->actingAsUser();
        Redis::set("sms_code:login:{$user->mobile}", '123456', 'EX', 300);

        $response = $this->post('/api/auth/sms-login', ['mobile' => $user->mobile, 'code' => '123456'])->assertOk();

        $this->assertSame($user->id, $response->data()['user_info']['id']);
    }

    public function test_info_returns_the_narrowed_row_without_password_or_wechat_columns(): void
    {
        $user = $this->actingAsUser();

        $data = $this->get('/api/auth/info', [], $user->token)->assertOk()->data();

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('deleted_at', $data);
        foreach (['openid', 'oa_openid', 'unionid', 'mini_openid'] as $column) {
            $this->assertArrayNotHasKey($column, $data);
        }
        $this->assertSame($user->id, $data['id']);
        $this->assertSame($user->mobile, $data['mobile']);
    }

    public function test_info_requires_authentication(): void
    {
        $this->get('/api/auth/info')->assertCode(401);
    }

    public function test_refresh_token_issues_a_new_token_and_invalidates_the_old_one(): void
    {
        $user = $this->actingAsUser();

        $newToken = (string) $this->post('/api/auth/refresh-token', [], $user->token)->assertOk()->data()['token'];

        $this->get('/api/auth/info', [], $user->token)->assertCode(401);
        $this->get('/api/auth/info', [], $newToken)->assertOk();
    }

    public function test_logout_blacklists_the_current_token(): void
    {
        $user = $this->actingAsUser();

        $this->post('/api/auth/logout', [], $user->token)->assertOk();

        $this->get('/api/auth/info', [], $user->token)->assertCode(401);
    }
}
