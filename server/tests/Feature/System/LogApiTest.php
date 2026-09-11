<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\AdminLoginLogRepository;
use app\repository\system\AdminOperationLogRepository;
use core\context\RequestContext;
use core\datascope\DataScope;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

final class LogApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/log';

    /** @param array<string, mixed> $overrides */
    private function loginLog(int $adminId, string $username, array $overrides = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('admin_login_logs')->insertGetId(array_merge([
            'admin_id'      => $adminId,
            'username'      => $username,
            'ip'            => '10.0.0.1',
            'user_agent'    => 'phpunit',
            'login_time'    => $now,
            'login_result'  => 1,
            'login_message' => '登录成功',
            'browser'       => 'Unknown',
            'os'            => 'Unknown',
            'created_at'    => $now,
        ], $overrides));
        $this->track('admin_login_logs', $id);

        return $id;
    }

    /** @param array<string, mixed> $overrides */
    private function operationLog(int $adminId, string $username, array $overrides = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('admin_operation_logs')->insertGetId(array_merge([
            'admin_id'       => $adminId,
            'username'       => $username,
            'method'         => 'POST',
            'path'           => '/adminapi/system/admin',
            'ip'             => '10.0.0.1',
            'user_agent'     => 'phpunit',
            'action'         => '创建管理员',
            'description'    => '创建新的管理员账户',
            'params'         => json_encode(['username' => 'x', 'password' => '***']),
            'result'         => json_encode(['code' => 200, 'message' => '创建成功'], JSON_UNESCAPED_UNICODE),
            'operation_time' => $now,
            'execution_time' => 0.012,
            'created_at'     => $now,
        ], $overrides));
        $this->track('admin_operation_logs', $id);

        return $id;
    }

    /** 本用例专属的用户名前缀（keyword 过滤只看自己造的行）。 */
    private function prefix(): string
    {
        return 'lg' . bin2hex(random_bytes(3));
    }

    /** @param array<string, mixed> $data @return list<int> */
    private function ids(array $data): array
    {
        return array_map('intval', array_column($data['list'], 'id'));
    }

    /**
     * viewer 与 inA 在部门 A，inB 在部门 B；viewer 的数据范围是「本部门」。
     *
     * @param list<string> $permissions
     * @return array{0: TestAdmin, 1: TestAdmin, 2: TestAdmin}
     */
    private function scopedTrio(array $permissions): array
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();
        $viewer = $this->actingAsAdmin($permissions, ['department_id' => $deptA], ['data_scope' => DataScope::DEPT]);
        $inA = $this->actingAsAdmin([], ['department_id' => $deptA]);
        $inB = $this->actingAsAdmin([], ['department_id' => $deptB]);

        return [$viewer, $inA, $inB];
    }

    public function test_login_log_list_filters_and_shape(): void
    {
        $super = $this->actingAsAdmin('super');
        $p = $this->prefix();
        $ok = $this->loginLog($super->id, "{$p}_ok", ['ip' => '10.1.1.1', 'login_time' => '2021-06-01 10:00:00']);
        $failed = $this->loginLog($super->id, "{$p}_fail", ['ip' => '10.2.2.2', 'login_result' => 0, 'login_message' => '密码错误', 'login_time' => '2020-01-01 10:00:00']);
        $url = self::BASE . '/login';

        $all = $this->get($url, ['keyword' => $p], $super->token)->assertOk()->data();
        $this->assertSame([$failed, $ok], $this->ids($all), 'id 倒序');
        $this->assertSame(2, $all['pagination']['total']);
        foreach (['id', 'admin_id', 'username', 'ip', 'user_agent', 'login_time', 'login_result', 'login_message', 'browser', 'os'] as $key) {
            $this->assertArrayHasKey($key, $all['list'][0]);
        }
        $this->assertSame(0, $all['list'][0]['login_result'], '契约：login_result 是数字 1/0');

        $this->assertSame([$failed], $this->ids($this->get($url, ['keyword' => $p, 'login_result' => 0], $super->token)->assertOk()->data()));
        $this->assertSame([$failed], $this->ids($this->get($url, ['keyword' => $p, 'ip' => '10.2.2'], $super->token)->assertOk()->data()));
        $this->assertSame([$ok], $this->ids($this->get($url, ['keyword' => $p, 'start_date' => '2021-01-01'], $super->token)->assertOk()->data()));
        $this->assertSame([$failed], $this->ids($this->get($url, ['keyword' => $p, 'end_date' => '2020-01-01'], $super->token)->assertOk()->data()), '结束日期含当天');
        $this->assertSame([$ok], $this->ids($this->get($url, ['keyword' => $p, 'page_no' => 2, 'page_size' => 1], $super->token)->assertOk()->data()));

        $this->assertArrayHasKey('start_date', $this->get($url, ['start_date' => '2021/01/01'], $super->token)->assertCode(422)->data()['errors']);
        $this->assertArrayHasKey('login_result', $this->get($url, ['login_result' => 3], $super->token)->assertCode(422)->data()['errors']);
    }

    public function test_operation_log_list_filters_and_shape(): void
    {
        $super = $this->actingAsAdmin('super');
        $p = $this->prefix();
        $create = $this->operationLog($super->id, "{$p}_a", ['operation_time' => '2021-06-01 10:00:00']);
        $update = $this->operationLog($super->id, "{$p}_b", ['method' => 'PUT', 'path' => "/adminapi/system/role/{$p}", 'operation_time' => '2020-01-01 10:00:00']);
        $byAction = $this->operationLog($super->id, 'someone_else', ['action' => "删除{$p}"]);
        $url = self::BASE . '/operation';

        $all = $this->get($url, ['keyword' => $p], $super->token)->assertOk()->data();
        $this->assertSame([$byAction, $update, $create], $this->ids($all), 'keyword 同时匹配 username / action / description');
        $row = $all['list'][2];
        foreach (['id', 'admin_id', 'username', 'method', 'path', 'ip', 'user_agent', 'action', 'description', 'params', 'result', 'operation_time', 'execution_time'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
        // MySQL 的 JSON 列不保证按写入顺序返回对象成员（按 key 长度、再按字典序重排），
        // 所以按 key 排序后再比较——这里验证的是内容而非 MySQL 内部存储顺序。
        $params = $row['params'];
        ksort($params);
        $this->assertSame(['password' => '***', 'username' => 'x'], $params);
        $this->assertSame(['code' => 200, 'message' => '创建成功'], $row['result']);
        $this->assertSame(0.012, $row['execution_time']);
        $this->assertSame('2021-06-01 10:00:00', $row['operation_time']);

        $this->assertSame([$update], $this->ids($this->get($url, ['keyword' => $p, 'method' => 'put'], $super->token)->assertOk()->data()));
        $this->assertSame([$update], $this->ids($this->get($url, ['keyword' => $p, 'path' => "/role/{$p}"], $super->token)->assertOk()->data()));
        $this->assertSame([$byAction, $create], $this->ids($this->get($url, ['keyword' => $p, 'start_date' => '2021-01-01'], $super->token)->assertOk()->data()));
        $this->assertSame([$update], $this->ids($this->get($url, ['keyword' => $p, 'end_date' => '2020-01-01'], $super->token)->assertOk()->data()));
    }

    public function test_keyword_wildcards_are_literal(): void
    {
        $super = $this->actingAsAdmin('super');
        $p = $this->prefix();
        $percent = $this->loginLog($super->id, "{$p}a%b");
        $this->loginLog($super->id, "{$p}axb");
        $underscore = $this->operationLog($super->id, "{$p}c_d");
        $this->operationLog($super->id, "{$p}cxd");

        $this->assertSame([$percent], $this->ids($this->get(self::BASE . '/login', ['keyword' => "{$p}a%b"], $super->token)->assertOk()->data()));
        $this->assertSame([$underscore], $this->ids($this->get(self::BASE . '/operation', ['keyword' => "{$p}c_d"], $super->token)->assertOk()->data()));
    }

    public function test_lists_and_totals_are_limited_to_the_viewers_data_scope(): void
    {
        [$viewer, $inA, $inB] = $this->scopedTrio(['system.log.login', 'system.log.operation']);
        $super = $this->actingAsAdmin('super');
        $p = $this->prefix();
        $loginA = [$this->loginLog($inA->id, "{$p}_a1"), $this->loginLog($inA->id, "{$p}_a2")];
        $this->loginLog($inB->id, "{$p}_b");
        $this->loginLog(0, "{$p}_ghost"); // 用户名不存在时 admin_id=0，不属于任何部门
        $opA = [$this->operationLog($inA->id, "{$p}_a1"), $this->operationLog($inA->id, "{$p}_a2")];
        $this->operationLog($inB->id, "{$p}_b");

        $login = $this->get(self::BASE . '/login', ['keyword' => $p], $viewer->token)->assertOk()->data();
        $this->assertEqualsCanonicalizing($loginA, $this->ids($login));
        $this->assertSame(2, $login['pagination']['total']);
        $paged = $this->get(self::BASE . '/login', ['keyword' => $p, 'limit' => 1, 'page' => 2], $viewer->token)->assertOk()->data();
        $this->assertSame(2, $paged['pagination']['total'], '分页总数按范围统计');
        $this->assertSame(2, $paged['pagination']['last_page']);
        $this->assertSame(4, $this->get(self::BASE . '/login', ['keyword' => $p], $super->token)->assertOk()->data()['pagination']['total']);

        $operation = $this->get(self::BASE . '/operation', ['keyword' => $p], $viewer->token)->assertOk()->data();
        $this->assertEqualsCanonicalizing($opA, $this->ids($operation));
        $this->assertSame(2, $operation['pagination']['total']);
        $this->assertSame(3, $this->get(self::BASE . '/operation', ['keyword' => $p], $super->token)->assertOk()->data()['pagination']['total']);
    }

    public function test_deleting_an_out_of_scope_log_is_not_found_and_keeps_the_row(): void
    {
        [$viewer, $inA, $inB] = $this->scopedTrio(['system.log.delete']);
        $p = $this->prefix();
        $outLogin = $this->loginLog($inB->id, "{$p}_b");
        $inLogin = $this->loginLog($inA->id, "{$p}_a");
        $outOp = $this->operationLog($inB->id, "{$p}_b");
        $inOp = $this->operationLog($inA->id, "{$p}_a");

        $this->assertSame(lang('messages.data_not_found'), $this->delete(self::BASE . "/login/{$outLogin}", [], $viewer->token)->assertCode(404)->message());
        $this->delete(self::BASE . "/operation/{$outOp}", [], $viewer->token)->assertCode(404);
        $this->delete(self::BASE . '/login/999999999', [], $viewer->token)->assertCode(404);
        $this->assertTrue(Db::table('admin_login_logs')->where('id', $outLogin)->exists());
        $this->assertTrue(Db::table('admin_operation_logs')->where('id', $outOp)->exists());

        $this->delete(self::BASE . "/login/{$inLogin}", [], $viewer->token)->assertOk();
        $this->delete(self::BASE . "/operation/{$inOp}", [], $viewer->token)->assertOk();
        $this->assertFalse(Db::table('admin_login_logs')->where('id', $inLogin)->exists());
        $this->assertFalse(Db::table('admin_operation_logs')->where('id', $inOp)->exists());
    }

    public function test_clear_only_removes_rows_in_scope(): void
    {
        [$viewer, $inA, $inB] = $this->scopedTrio(['system.log.clear']);
        $p = $this->prefix();
        $opIn = [$this->operationLog($inA->id, "{$p}_1"), $this->operationLog($inA->id, "{$p}_2")];
        $opOut = [$this->operationLog($inB->id, "{$p}_3"), $this->operationLog(0, "{$p}_4")];
        $loginIn = [$this->loginLog($inA->id, "{$p}_1"), $this->loginLog($inA->id, "{$p}_2")];
        $loginOut = [$this->loginLog($inB->id, "{$p}_3"), $this->loginLog(0, "{$p}_4")];

        // 先清操作日志：此前 viewer 没发过写请求（Task 9 起写请求本身也会记一条操作日志，归 viewer 所有）
        $response = $this->post(self::BASE . '/operation/clear', [], $viewer->token)->assertOk();
        $this->assertSame(lang('messages.clear_success'), $response->message());
        $this->assertSame(['count' => 2], $response->data());
        $this->assertSame(0, Db::table('admin_operation_logs')->whereIn('id', $opIn)->count());
        $this->assertSame(2, Db::table('admin_operation_logs')->whereIn('id', $opOut)->count());

        $this->assertSame(['count' => 2], $this->post(self::BASE . '/login/clear', [], $viewer->token)->assertOk()->data());
        $this->assertSame(0, Db::table('admin_login_logs')->whereIn('id', $loginIn)->count());
        $this->assertSame(2, Db::table('admin_login_logs')->whereIn('id', $loginOut)->count());
    }

    public function test_each_endpoint_requires_its_permission(): void
    {
        $reader = $this->actingAsAdmin(['system.log.login']);

        $this->get(self::BASE . '/login', [], $reader->token)->assertOk();
        $this->get(self::BASE . '/operation', [], $reader->token)->assertCode(403);
        $this->delete(self::BASE . '/login/1', [], $reader->token)->assertCode(403);
        $this->post(self::BASE . '/login/clear', [], $reader->token)->assertCode(403);
        $this->post(self::BASE . '/operation/clear', [], $reader->token)->assertCode(403);
    }

    public function test_repositories_record_under_an_acting_admin_without_created_by(): void
    {
        $admin = $this->actingAsAdmin();
        RequestContext::setActingUser($admin->id);

        (new AdminOperationLogRepository())->record([
            'admin_id'       => $admin->id,
            'username'       => $admin->username,
            'method'         => 'POST',
            'path'           => str_repeat('/x', 200),
            'ip'             => '127.0.0.1',
            'action'         => '动作',
            'description'    => '描述',
            'params'         => ['k' => '中文'],
            'result'         => ['code' => 200, 'message' => 'ok'],
            'execution_time' => 0.5,
        ]);
        (new AdminLoginLogRepository())->record(['admin_id' => $admin->id, 'username' => $admin->username, 'login_result' => true]);

        $op = Db::table('admin_operation_logs')->where('admin_id', $admin->id)->first();
        $this->assertNotNull($op, '受控表的 create() 不能去填不存在的 created_by 列');
        $this->assertSame(255, strlen((string) $op->path), 'path 截断到列宽');
        $this->assertSame(['k' => '中文'], json_decode((string) $op->params, true));
        $this->assertNotNull($op->operation_time);
        $this->assertSame(1, Db::table('admin_login_logs')->where('admin_id', $admin->id)->count());
    }

    public function test_log_menus_are_seeded_with_tp8_ids(): void
    {
        $this->assertSame(
            [110 => 'system.log', 111 => 'system.log.login', 112 => 'system.log.operation', 113 => 'system.log.delete', 114 => 'system.log.clear'],
            Db::table('menus')->whereBetween('id', [110, 114])->orderBy('id')->pluck('permission', 'id')->all()
        );
        $this->assertSame('/system/log/operation', Db::table('menus')->where('id', 112)->value('component'));
    }
}
