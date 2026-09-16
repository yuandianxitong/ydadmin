<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use app\model\user\BalanceLog;
use app\model\user\PointsLog;
use app\service\user\BalanceService;
use app\service\user\PointsService;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

/** spec §6.2：会员自助（资料、改密、余额、积分、两个流水列表）。全部端点挂 ApiAuthMiddleware。 */
final class UserApiTest extends ApiTestCase
{
    public function test_profile_excludes_password_deleted_at_and_wechat_columns(): void
    {
        $user = $this->actingAsUser();

        $data = $this->get('/api/user/profile', [], $user->token)->assertOk()->data();

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('deleted_at', $data);
        foreach (['openid', 'oa_openid', 'unionid', 'mini_openid'] as $column) {
            $this->assertArrayNotHasKey($column, $data);
        }
        $this->assertSame($user->id, $data['id']);
        $this->assertSame($user->mobile, $data['mobile']);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->get('/api/user/profile')->assertCode(401);
    }

    public function test_update_profile_writes_only_the_allowed_fields(): void
    {
        $user = $this->actingAsUser();

        $this->put('/api/user/profile', [
            'nickname' => '小明',
            'avatar'   => 'https://example.com/a.png',
            'gender'   => 1,
            'birthday' => '2000-01-01',
        ], $user->token)->assertOk();

        $row = Db::table('users')->where('id', $user->id)->first();
        $this->assertSame('小明', $row->nickname);
        $this->assertSame('https://example.com/a.png', $row->avatar);
        $this->assertSame(1, (int) $row->gender);
        $this->assertSame('2000-01-01', substr((string) $row->birthday, 0, 10));
    }

    public function test_update_profile_rejects_an_invalid_gender(): void
    {
        $user = $this->actingAsUser();

        $response = $this->put('/api/user/profile', ['gender' => 9], $user->token)->assertCode(422);
        $this->assertSame(lang('validation.gender_invalid'), $response->data()['errors']['gender']);
    }

    public function test_change_password_bumps_token_version_and_invalidates_the_old_token(): void
    {
        $user = $this->actingAsUser();

        $this->put('/api/user/change-password', ['old_password' => $user->password, 'new_password' => 'NewPassw0rd!'], $user->token)->assertOk();

        $this->get('/api/user/profile', [], $user->token)->assertCode(401);
        $login = $this->post('/api/auth/login', ['account' => $user->mobile, 'password' => 'NewPassw0rd!'])->assertOk();
        $this->assertNotEmpty($login->data()['token']);
    }

    public function test_change_password_rejects_a_wrong_old_password(): void
    {
        $user = $this->actingAsUser();

        $response = $this->put('/api/user/change-password', ['old_password' => 'WrongPass1', 'new_password' => 'NewPassw0rd!'], $user->token)->assertCode(400);

        $this->assertSame(lang('auth.old_password_error'), $response->message());
        $this->get('/api/user/profile', [], $user->token)->assertOk(); // 校验失败不吊销旧 token
    }

    public function test_balance_and_points_return_typed_values(): void
    {
        $user = $this->actingAsUser();
        Container::get(BalanceService::class)->change($user->id, 15.5, BalanceLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '余额测试');
        Container::get(PointsService::class)->change($user->id, 8, PointsLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '积分测试');

        $this->assertSame(['balance' => '15.50'], $this->get('/api/user/balance', [], $user->token)->assertOk()->data());
        $this->assertSame(['points' => 8], $this->get('/api/user/points', [], $user->token)->assertOk()->data());
    }

    public function test_balance_logs_are_scoped_to_the_authenticated_user(): void
    {
        $me = $this->actingAsUser();
        $other = $this->actingAsUser();
        Container::get(BalanceService::class)->change($me->id, 20.00, BalanceLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '本人流水');
        Container::get(BalanceService::class)->change($other->id, 30.00, BalanceLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '他人流水');

        $mine = $this->get('/api/user/balance-logs', ['page' => 1, 'limit' => 100], $me->token)->assertOk()->data()['list'];
        $theirs = $this->get('/api/user/balance-logs', ['page' => 1, 'limit' => 100], $other->token)->assertOk()->data()['list'];

        $this->assertSame(['本人流水'], array_column($mine, 'remark'));
        $this->assertSame(['他人流水'], array_column($theirs, 'remark'));
    }

    public function test_balance_logs_pagination_accepts_both_admin_and_c_end_param_names(): void
    {
        $user = $this->actingAsUser();
        Container::get(BalanceService::class)->change($user->id, 5.00, BalanceLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '分页测试');

        $adminStyle = $this->get('/api/user/balance-logs', ['page' => 1, 'limit' => 10], $user->token)->assertOk()->data();
        $cEndStyle = $this->get('/api/user/balance-logs', ['page_no' => 1, 'page_size' => 10], $user->token)->assertOk()->data();

        $this->assertSame($adminStyle['pagination'], $cEndStyle['pagination']);
        $this->assertNotEmpty($cEndStyle['list']);
        $this->assertArrayHasKey('type_text', $cEndStyle['list'][0]);
    }

    public function test_points_logs_are_scoped_to_the_authenticated_user(): void
    {
        $me = $this->actingAsUser();
        $other = $this->actingAsUser();
        Container::get(PointsService::class)->change($me->id, 3, PointsLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '本人积分');
        Container::get(PointsService::class)->change($other->id, 7, PointsLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '他人积分');

        $mine = $this->get('/api/user/points-logs', ['page_no' => 1, 'page_size' => 100], $me->token)->assertOk()->data()['list'];

        $this->assertSame(['本人积分'], array_column($mine, 'remark'));
    }
}
