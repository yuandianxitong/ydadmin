<?php

declare(strict_types=1);

namespace tests\Feature\User;

use app\model\user\BalanceLog;
use app\model\user\PointsLog;
use core\auth\TokenVersion;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;

/** spec §6.3：/adminapi/user 的 list、detail、adjust-balance、adjust-points、status、balance-logs、points-logs。 */
final class UserManageApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/user';

    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        foreach ($this->userIds as $id) {
            Db::table('balance_logs')->where('user_id', $id)->delete();
            Db::table('points_logs')->where('user_id', $id)->delete();
            Redis::del("user_token_ver:{$id}");
        }
        $this->userIds = [];
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $id = (int) Db::table('users')->insertGetId(array_merge([
            'nickname'        => "会员{$suffix}",
            'avatar'          => '',
            'mobile'          => '19' . str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT),
            'password'        => password_hash('Passw0rd!', PASSWORD_DEFAULT),
            'status'          => 1,
            'balance'         => '100.00',
            'points'          => 100,
            'last_login_ip'   => '127.0.0.1',
            'last_login_time' => $now,
            'login_count'     => 3,
            'created_at'      => $now,
            'updated_at'      => $now,
        ], $attributes));
        $this->track('users', $id);
        $this->userIds[] = $id;

        return $id;
    }

    public function test_every_endpoint_requires_its_own_permission(): void
    {
        $nobody = $this->actingAsAdmin();
        $userId = $this->createUser();

        $this->get(self::BASE . '/list', [], $nobody->token)->assertCode(403);
        $this->get(self::BASE . "/detail/{$userId}", [], $nobody->token)->assertCode(403);
        $this->post(self::BASE . '/adjust-balance', ['user_id' => $userId, 'amount' => 1], $nobody->token)->assertCode(403);
        $this->post(self::BASE . '/adjust-points', ['user_id' => $userId, 'points' => 1], $nobody->token)->assertCode(403);
        $this->put(self::BASE . "/{$userId}/status", ['status' => 0], $nobody->token)->assertCode(403);
        $this->get(self::BASE . '/balance-logs', [], $nobody->token)->assertCode(403);
        $this->get(self::BASE . '/points-logs', [], $nobody->token)->assertCode(403);
    }

    public function test_list_rows_match_the_admin_ui_contract(): void
    {
        $admin = $this->actingAsAdmin(['user.list']);
        $userId = $this->createUser(['nickname' => '列表探针']);

        $data = $this->get(self::BASE . '/list', ['keyword' => '列表探针', 'page' => 1, 'limit' => 15], $admin->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($data['pagination']));
        $this->assertCount(1, $data['list']);
        $row = $data['list'][0];
        $this->assertSame([
            'id', 'nickname', 'avatar', 'mobile', 'balance', 'points', 'status',
            'last_login_ip', 'last_login_time', 'login_count', 'created_at',
        ], array_keys($row), '字段与 admin/src/types/user.d.ts 的 UserItem 逐字对齐');
        $this->assertSame($userId, $row['id']);
        $this->assertSame('100.00', $row['balance'], 'balance 是字符串');
        $this->assertSame(100, $row['points'], 'points 是数字');
        $this->assertSame(1, $row['status']);
        $this->assertStringNotContainsString('password', json_encode($row, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function test_list_filters_by_keyword_and_status(): void
    {
        $admin = $this->actingAsAdmin(['user.list']);
        $enabled = $this->createUser(['nickname' => '启用探针' . bin2hex(random_bytes(2))]);
        $disabled = $this->createUser(['nickname' => '禁用探针' . bin2hex(random_bytes(2)), 'status' => 0]);
        $mobile = (string) Db::table('users')->where('id', $enabled)->value('mobile');

        $byStatus = $this->get(self::BASE . '/list', ['status' => 0, 'limit' => 100], $admin->token)->assertOk()->data()['list'];
        $ids = array_column($byStatus, 'id');
        $this->assertContains($disabled, $ids);
        $this->assertNotContains($enabled, $ids);

        $byMobile = $this->get(self::BASE . '/list', ['keyword' => $mobile], $admin->token)->assertOk()->data()['list'];
        $this->assertSame([$enabled], array_column($byMobile, 'id'), 'keyword 也匹配手机号');
    }

    public function test_detail_returns_the_row_without_the_password(): void
    {
        $admin = $this->actingAsAdmin(['user.detail']);
        $userId = $this->createUser();

        $data = $this->get(self::BASE . "/detail/{$userId}", [], $admin->token)->assertOk()->data();
        $this->assertSame($userId, $data['id']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayHasKey('openid', $data, '管理端详情保留微信列（spec §6.3）');
        $this->assertArrayHasKey('unionid', $data);

        $this->get(self::BASE . '/detail/999999999', [], $admin->token)->assertCode(404);
    }

    public function test_adjust_balance_goes_through_the_single_entry_and_records_the_operator(): void
    {
        $admin = $this->actingAsAdmin(['user.adjust-balance']);
        $userId = $this->createUser();

        $this->post(self::BASE . '/adjust-balance', ['user_id' => $userId, 'amount' => -30.5, 'remark' => '扣款'], $admin->token)->assertOk();

        $this->assertSame('69.50', (string) Db::table('users')->where('id', $userId)->value('balance'));
        $log = Db::table('balance_logs')->where('user_id', $userId)->first();
        $this->assertSame('-30.50', (string) $log->amount);
        $this->assertSame('100.00', (string) $log->before_balance);
        $this->assertSame('69.50', (string) $log->after_balance);
        $this->assertSame(BalanceLog::TYPE_ADMIN_ADJUST, (int) $log->type);
        $this->assertSame('admin_adjust', (string) $log->source);
        $this->assertSame('扣款', (string) $log->remark);
        $this->assertSame($admin->id, (int) $log->operator_id);
    }

    public function test_adjust_balance_rejects_a_negative_result_and_a_zero_amount(): void
    {
        $admin = $this->actingAsAdmin(['user.adjust-balance']);
        $userId = $this->createUser();

        $tooMuch = $this->post(self::BASE . '/adjust-balance', ['user_id' => $userId, 'amount' => -100.01], $admin->token)->assertCode(422);
        $this->assertSame(lang('validation.balance_not_enough'), $tooMuch->data()['errors']['amount']);

        $zero = $this->post(self::BASE . '/adjust-balance', ['user_id' => $userId, 'amount' => 0], $admin->token)->assertCode(422);
        $this->assertSame(lang('validation.amount_zero'), $zero->data()['errors']['amount']);

        $this->assertSame('100.00', (string) Db::table('users')->where('id', $userId)->value('balance'));
        $this->assertSame(0, Db::table('balance_logs')->where('user_id', $userId)->count());
    }

    public function test_adjust_balance_rejects_more_than_two_decimal_places(): void
    {
        $admin = $this->actingAsAdmin(['user.adjust-balance']);
        $userId = $this->createUser();

        $resp = $this->post(self::BASE . '/adjust-balance', ['user_id' => $userId, 'amount' => 1.234], $admin->token)->assertCode(422);
        $this->assertSame(lang('validation.amount_invalid'), $resp->data()['errors']['amount']);
        $this->assertSame('100.00', (string) Db::table('users')->where('id', $userId)->value('balance'), '三位小数被拒绝，不应静默取整');
    }

    public function test_adjust_points_goes_through_the_single_entry(): void
    {
        $admin = $this->actingAsAdmin(['user.adjust-points']);
        $userId = $this->createUser();

        $this->post(self::BASE . '/adjust-points', ['user_id' => $userId, 'points' => 50, 'remark' => '补发'], $admin->token)->assertOk();

        $this->assertSame(150, (int) Db::table('users')->where('id', $userId)->value('points'));
        $log = Db::table('points_logs')->where('user_id', $userId)->first();
        $this->assertSame(50, (int) $log->points);
        $this->assertSame(100, (int) $log->before_points);
        $this->assertSame(150, (int) $log->after_points);
        $this->assertSame(PointsLog::TYPE_ADMIN_ADJUST, (int) $log->type);
        $this->assertSame('admin_adjust', (string) $log->source);
        $this->assertSame($admin->id, (int) $log->operator_id);

        $tooMuch = $this->post(self::BASE . '/adjust-points', ['user_id' => $userId, 'points' => -151], $admin->token)->assertCode(422);
        $this->assertSame(lang('validation.points_not_enough'), $tooMuch->data()['errors']['points']);
    }

    public function test_disabling_a_user_revokes_their_tokens(): void
    {
        $admin = $this->actingAsAdmin(['user.status']);
        $userId = $this->createUser();
        $versionBefore = TokenVersion::current($userId, 'user');

        $this->put(self::BASE . "/{$userId}/status", ['status' => 0], $admin->token)->assertOk();

        $this->assertSame(0, (int) Db::table('users')->where('id', $userId)->value('status'));
        $this->assertSame($versionBefore + 1, TokenVersion::current($userId, 'user'), '禁用必须自增 user scope 的 token 版本号');

        $this->put(self::BASE . "/{$userId}/status", ['status' => 1], $admin->token)->assertOk();
        $this->assertSame(1, (int) Db::table('users')->where('id', $userId)->value('status'));
        $this->assertSame($versionBefore + 1, TokenVersion::current($userId, 'user'), '启用不吊销');

        $this->put(self::BASE . "/{$userId}/status", ['status' => 2], $admin->token)->assertCode(422);
        $this->put(self::BASE . '/999999999/status', ['status' => 0], $admin->token)->assertCode(404);
    }

    public function test_balance_logs_carry_the_user_and_operator_names_and_filter(): void
    {
        $admin = $this->actingAsAdmin(['user.adjust-balance', 'user.balance-logs']);
        $userId = $this->createUser(['nickname' => '流水探针' . bin2hex(random_bytes(2))]);
        $nickname = (string) Db::table('users')->where('id', $userId)->value('nickname');
        $this->post(self::BASE . '/adjust-balance', ['user_id' => $userId, 'amount' => 10, 'remark' => '加钱'], $admin->token)->assertOk();

        $data = $this->get(self::BASE . '/balance-logs', ['keyword' => $nickname, 'type' => BalanceLog::TYPE_ADMIN_ADJUST], $admin->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertCount(1, $data['list']);
        $row = $data['list'][0];
        foreach (['id', 'user_id', 'user_nickname', 'amount', 'before_balance', 'after_balance', 'type', 'type_text', 'source', 'remark', 'operator_id', 'operator_name', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $row, "缺字段 {$field}（admin/src/types/user.d.ts 的 BalanceLogItem）");
        }
        $this->assertSame($nickname, $row['user_nickname']);
        $this->assertSame(lang('business.balance_log_type_4'), $row['type_text']);
        $this->assertSame($admin->id, $row['operator_id']);
        $this->assertSame($admin->username, $row['operator_name'], 'TestAdmin 没有 nickname 属性；actingAsAdmin() 建号时把 nickname 设成与 username 同值');

        $this->assertSame([], $this->get(self::BASE . '/balance-logs', ['keyword' => $nickname, 'type' => BalanceLog::TYPE_RECHARGE], $admin->token)->assertOk()->data()['list'], 'type 过滤生效');
        $this->assertSame([], $this->get(self::BASE . '/balance-logs', [
            'keyword'    => $nickname,
            'start_date' => date('Y-m-d', strtotime('-10 days')),
            'end_date'   => date('Y-m-d', strtotime('-9 days')),
        ], $admin->token)->assertOk()->data()['list'], '日期区间过滤生效');
    }

    public function test_points_logs_carry_the_user_and_operator_names(): void
    {
        $admin = $this->actingAsAdmin(['user.adjust-points', 'user.points-logs']);
        $userId = $this->createUser(['nickname' => '积分探针' . bin2hex(random_bytes(2))]);
        $nickname = (string) Db::table('users')->where('id', $userId)->value('nickname');
        $this->post(self::BASE . '/adjust-points', ['user_id' => $userId, 'points' => -20, 'remark' => '扣分'], $admin->token)->assertOk();

        $rows = $this->get(self::BASE . '/points-logs', ['keyword' => $nickname], $admin->token)->assertOk()->data()['list'];
        $this->assertCount(1, $rows);
        foreach (['id', 'user_id', 'user_nickname', 'points', 'before_points', 'after_points', 'type', 'type_text', 'source', 'remark', 'operator_id', 'operator_name', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $rows[0], "缺字段 {$field}（PointsLogItem）");
        }
        $this->assertSame(-20, $rows[0]['points']);
        $this->assertSame($nickname, $rows[0]['user_nickname']);
        $this->assertSame(lang('business.points_log_type_1'), $rows[0]['type_text']);
        $this->assertSame($admin->id, $rows[0]['operator_id']);
    }
}
