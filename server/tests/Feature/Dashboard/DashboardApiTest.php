<?php

declare(strict_types=1);

namespace tests\Feature\Dashboard;

use core\datascope\DataScope;
use core\datascope\DataScopeResolver;
use support\Cache;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

final class DashboardApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/dashboard';

    private const ENDPOINTS = ['/stats', '/recent-logs', '/recent-activities', '/active-ranking'];

    /** 写一条登录日志。管理员由 trackAdmin() 登记过，tearDown 会连同其登录日志一起清掉。 */
    private function loginLog(TestAdmin $admin, bool $success, int $secondsAgo = 0): void
    {
        Db::table('admin_login_logs')->insert([
            'admin_id'      => $admin->id,
            'username'      => $admin->username,
            'ip'            => '127.0.0.1',
            'user_agent'    => 'phpunit',
            'login_time'    => date('Y-m-d H:i:s', time() - $secondsAgo),
            'login_result'  => $success ? 1 : 0,
            'login_message' => $success ? '登录成功' : '密码错误',
            'browser'       => 'Unknown',
            'os'            => 'Unknown',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    private function operationLog(TestAdmin $admin, string $description, int $secondsAgo = 0): void
    {
        $id = (int) Db::table('admin_operation_logs')->insertGetId([
            'admin_id'       => $admin->id,
            'username'       => $admin->username,
            'method'         => 'POST',
            'path'           => '/adminapi/dashboard-test',
            'ip'             => '127.0.0.1',
            'user_agent'     => 'phpunit',
            'action'         => '测试',
            'description'    => $description,
            'params'         => '{}',
            'result'         => '{"code":200,"message":"ok"}',
            'operation_time' => date('Y-m-d H:i:s', time() - $secondsAgo),
            'execution_time' => 0.012,
        ]);
        $this->track('admin_operation_logs', $id);
    }

    /**
     * 部门 A 里一个按「本部门」看数的 viewer 和一个同事，部门 B 里一个外人。
     *
     * @return array{0: TestAdmin, 1: TestAdmin, 2: TestAdmin, 3: int} [viewer, colleague, outsider, 部门 A]
     */
    private function scopedTrio(): array
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();
        $viewer = $this->actingAsAdmin([], ['department_id' => $deptA], ['data_scope' => DataScope::DEPT]);
        $colleague = $this->actingAsAdmin([], ['department_id' => $deptA]);
        $outsider = $this->actingAsAdmin([], ['department_id' => $deptB]);

        return [$viewer, $colleague, $outsider, $deptA];
    }

    /** 与 DashboardService::remember() 同口径拼出 stats 的缓存键：dashboard.stats.{管理员 id}.{数据范围指纹}.{days}。 */
    private function statsKey(int $adminId, int $days = 7): string
    {
        $snapshot = Container::get(DataScopeResolver::class)->resolve($adminId);

        return 'dashboard.stats.' . $adminId . '.' . crc32((string) json_encode($snapshot->toArray())) . '.' . $days;
    }

    public function test_all_endpoints_need_login_but_no_permission(): void
    {
        foreach (self::ENDPOINTS as $path) {
            $this->get(self::BASE . $path)->assertCode(401);
        }

        $nobody = $this->actingAsAdmin();
        foreach (self::ENDPOINTS as $path) {
            $this->get(self::BASE . $path, [], $nobody->token)->assertOk();
        }
    }

    public function test_stats_shape_matches_the_workbench(): void
    {
        $admin = $this->actingAsAdmin();

        $data = $this->get(self::BASE . '/stats', [], $admin->token)->assertOk()->data();
        $this->assertSame([
            'adminCount', 'roleCount', 'menuCount', 'configCount',
            'todayLoginCount', 'todayNewUsers', 'activeUsers', 'totalUsers',
            'trends', 'operationLogCount', 'loginTrend', 'registerTrend',
        ], array_keys($data));
        foreach (['adminCount', 'roleCount', 'menuCount', 'configCount', 'todayLoginCount', 'operationLogCount'] as $key) {
            $this->assertIsInt($data[$key], $key);
        }

        // C 端用户字段：M5 接入前为 0 / []
        $this->assertSame(0, $data['totalUsers']);
        $this->assertSame(0, $data['activeUsers']);
        $this->assertSame(0, $data['todayNewUsers']);
        $this->assertSame([], $data['registerTrend']);
        $this->assertSame(['totalUsers', 'activeUsers', 'todayNewUsers', 'todayLoginCount'], array_keys($data['trends']));
        $this->assertSame(['value' => 0, 'type' => 'up'], $data['trends']['totalUsers']);
        $this->assertSame(['value' => 0, 'type' => 'up', 'unit' => 'percent'], $data['trends']['activeUsers']);
        $this->assertSame(['value' => 0, 'type' => 'up'], $data['trends']['todayNewUsers']);
        $this->assertSame(['value', 'type'], array_keys($data['trends']['todayLoginCount']));

        // 登录趋势：默认 7 天，含今天，日期格式 m-d（与 TP8 一致）
        $this->assertCount(7, $data['loginTrend']);
        $this->assertSame(['date', 'count'], array_keys($data['loginTrend'][0]));
        $this->assertSame(date('m-d', strtotime('-6 days')), $data['loginTrend'][0]['date']);
        $this->assertSame(date('m-d'), $data['loginTrend'][6]['date']);
    }

    public function test_stats_follow_the_viewers_data_scope(): void
    {
        [$viewer, $colleague, $outsider] = $this->scopedTrio();
        $this->loginLog($colleague, true);
        $this->loginLog($colleague, true);
        $this->loginLog($colleague, false);
        $this->loginLog($colleague, true, 7 * 86400); // 上周同日：只进环比，不进 7 天趋势
        $this->loginLog($outsider, true);
        $this->loginLog($outsider, true);
        $this->operationLog($colleague, '本部门的操作');
        $this->operationLog($outsider, '外部门的操作');

        $stats = $this->get(self::BASE . '/stats', [], $viewer->token)->assertOk()->data();
        $this->assertSame(2, $stats['adminCount'], '部门 A 只有 viewer 与同事');
        $this->assertSame(2, $stats['todayLoginCount'], '只数范围内、登录成功的');
        $this->assertSame(['value' => 1, 'type' => 'up'], $stats['trends']['todayLoginCount'], '今天 2 次，上周同日 1 次');
        $this->assertSame(1, $stats['operationLogCount']);
        $this->assertSame(2, array_sum(array_column($stats['loginTrend'], 'count')));
        $this->assertSame(['date' => date('m-d'), 'count' => 2], $stats['loginTrend'][6]);

        $super = $this->actingAsAdmin('super');
        $all = $this->get(self::BASE . '/stats', [], $super->token)->assertOk()->data();
        $this->assertGreaterThanOrEqual(4, $all['todayLoginCount']);
        $this->assertGreaterThanOrEqual(2, $all['operationLogCount']);
        $this->assertGreaterThanOrEqual(4, $all['adminCount']);
    }

    public function test_days_is_clamped_to_1_through_90(): void
    {
        $admin = $this->actingAsAdmin();

        foreach ([[30, 30], [1000, 90], [0, 1], [-5, 1], ['abc', 7], ['', 7]] as [$days, $expected]) {
            $trend = $this->get(self::BASE . '/stats', ['days' => $days], $admin->token)->assertOk()->data()['loginTrend'];
            $this->assertCount($expected, $trend, 'days=' . var_export($days, true));
        }
    }

    public function test_results_are_cached_per_admin_for_five_minutes(): void
    {
        $dept = $this->createDepartment();
        $viewer = $this->actingAsAdmin([], ['department_id' => $dept], ['data_scope' => DataScope::DEPT]);
        $colleague = $this->actingAsAdmin([], ['department_id' => $dept]);
        $this->loginLog($colleague, true);

        $this->assertSame(1, $this->get(self::BASE . '/stats', [], $viewer->token)->data()['todayLoginCount']);
        $key = $this->statsKey($viewer->id);
        $this->assertTrue(Cache::has($key), '缓存键为 dashboard.{接口}.{管理员 id}.{数据范围指纹}.{参数}');
        $ttl = (int) Redis::ttl($key);
        $this->assertGreaterThan(240, $ttl);
        $this->assertLessThanOrEqual(300, $ttl);

        $this->loginLog($colleague, true);
        $this->assertSame(1, $this->get(self::BASE . '/stats', [], $viewer->token)->data()['todayLoginCount'], '5 分钟内命中缓存');

        $peer = $this->actingAsAdmin([], ['department_id' => $dept], ['data_scope' => DataScope::DEPT]);
        $this->assertSame(2, $this->get(self::BASE . '/stats', [], $peer->token)->data()['todayLoginCount'], '不同管理员不共用缓存');

        Cache::delete($key);
        $this->assertSame(2, $this->get(self::BASE . '/stats', [], $viewer->token)->data()['todayLoginCount']);
    }

    /**
     * 数据范围收窄必须立刻生效：缓存键带数据范围指纹，范围一变键就变，不用等 300 秒过期——
     * 否则被降权的管理员还能继续看到原来范围里的用户名、操作描述与 IP。
     */
    public function test_narrowing_the_scope_takes_effect_without_waiting_for_the_cache(): void
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();
        $viewer = $this->actingAsAdmin([], ['department_id' => $deptA], ['data_scope' => DataScope::CUSTOM, 'dept_ids' => [$deptA, $deptB]]);
        $outsider = $this->actingAsAdmin([], ['department_id' => $deptB]);
        $roleId = (int) Db::table('admin_roles')->where('admin_id', $viewer->id)->value('role_id');
        $this->loginLog($outsider, true);

        $this->assertSame(1, $this->get(self::BASE . '/stats', [], $viewer->token)->assertOk()->data()['todayLoginCount']);
        $this->assertSame([$outsider->username], array_column($this->get(self::BASE . '/recent-logs', [], $viewer->token)->assertOk()->data(), 'username'));

        // 收窄范围：自定义范围里去掉部门 B，并按业务写路径的做法清掉数据范围缓存
        Db::table('role_departments')->where('role_id', $roleId)->where('department_id', $deptB)->delete();
        Container::get(DataScopeResolver::class)->forget($viewer->id);

        $this->assertSame(0, $this->get(self::BASE . '/stats', [], $viewer->token)->assertOk()->data()['todayLoginCount'], '收窄后立刻生效');
        $this->assertSame([], $this->get(self::BASE . '/recent-logs', [], $viewer->token)->assertOk()->data(), '不能再读到部门 B 的登录日志');
    }

    public function test_recent_logs_are_the_latest_ten_in_scope(): void
    {
        [$viewer, $colleague, $outsider] = $this->scopedTrio();
        for ($i = 12; $i >= 1; $i--) {
            $this->loginLog($colleague, $i % 2 === 0, $i * 60);
        }
        $this->loginLog($outsider, true); // 最新的一条，但在范围外

        $logs = $this->get(self::BASE . '/recent-logs', [], $viewer->token)->assertOk()->data();
        $this->assertCount(10, $logs);
        $this->assertSame([$colleague->username], array_values(array_unique(array_column($logs, 'username'))));
        $times = array_column($logs, 'login_time');
        $sorted = $times;
        rsort($sorted);
        $this->assertSame($sorted, $times, '按登录时间倒序');
        foreach (['id', 'admin_id', 'username', 'ip', 'login_time', 'login_result', 'login_message', 'browser', 'os'] as $field) {
            $this->assertArrayHasKey($field, $logs[0]);
        }
        $this->assertIsInt($logs[0]['login_result']);
    }

    public function test_recent_activities_merge_both_logs_newest_first(): void
    {
        [$viewer, $colleague, $outsider] = $this->scopedTrio();
        $this->operationLog($colleague, '修改了系统配置', 10);
        $this->loginLog($colleague, true, 120);
        $this->loginLog($colleague, false, 2 * 3600 + 30);
        $this->operationLog($outsider, '外部门的操作');
        $this->loginLog($outsider, true);

        $items = $this->get(self::BASE . '/recent-activities', [], $viewer->token)->assertOk()->data();
        $this->assertSame(['operation', 'login_success', 'login_failed'], array_column($items, 'type'));
        $this->assertSame(['type', 'username', 'description', 'time', 'relative_time'], array_keys($items[0]));
        $this->assertSame([$colleague->username], array_values(array_unique(array_column($items, 'username'))));
        $this->assertSame("{$colleague->username} 修改了系统配置", $items[0]['description']);
        $this->assertSame("{$colleague->username} 登录系统", $items[1]['description']);
        $this->assertSame("{$colleague->username} 登录失败", $items[2]['description']);
        $this->assertSame('刚刚', $items[0]['relative_time']);
        $this->assertSame('2分钟前', $items[1]['relative_time']);
        $this->assertSame('2小时前', $items[2]['relative_time']);

        // 缓存里不存与语言有关的文案：同一个管理员换成英文，命中缓存，但文案是英文
        $en = $this->get(self::BASE . '/recent-activities', [], $viewer->token, ['think-lang' => 'en'])->assertOk()->data();
        $this->assertSame("{$colleague->username} signed in", $en[1]['description']);
        $this->assertSame('2 minutes ago', $en[1]['relative_time']);
    }

    public function test_recent_activities_keep_at_most_eight(): void
    {
        [$viewer, $colleague] = $this->scopedTrio();
        for ($i = 1; $i <= 6; $i++) {
            $this->loginLog($colleague, true, $i * 60);
            $this->operationLog($colleague, "操作{$i}", $i * 60 + 30);
        }

        $items = $this->get(self::BASE . '/recent-activities', [], $viewer->token)->assertOk()->data();
        $this->assertCount(8, $items, '两类日志各取最近 5 条，合并后取前 8 条');
        $this->assertNotContains("{$colleague->username} 操作6", array_column($items, 'description'));
    }

    public function test_active_ranking_by_period(): void
    {
        [$viewer, $colleague, $outsider, $deptA] = $this->scopedTrio();
        $second = $this->actingAsAdmin([], ['department_id' => $deptA]);
        $this->loginLog($colleague, true);
        $this->loginLog($colleague, true);
        $this->loginLog($colleague, true);
        $this->loginLog($colleague, true, 40 * 86400); // 40 天前：任何周期都不算
        $this->loginLog($second, true);
        $this->loginLog($second, false);               // 失败的不算
        for ($i = 0; $i < 5; $i++) {
            $this->loginLog($outsider, true);          // 范围外
        }

        $expected = [
            ['rank' => 1, 'username' => $colleague->username, 'count' => 3],
            ['rank' => 2, 'username' => $second->username, 'count' => 1],
        ];
        $this->assertSame(['period' => 'day', 'list' => $expected], $this->get(self::BASE . '/active-ranking', [], $viewer->token)->assertOk()->data());
        $this->assertSame(['period' => 'month', 'list' => $expected], $this->get(self::BASE . '/active-ranking', ['period' => 'month'], $viewer->token)->assertOk()->data());
        $this->assertSame('week', $this->get(self::BASE . '/active-ranking', ['period' => 'week'], $viewer->token)->assertOk()->data()['period']);

        foreach (['year', ''] as $period) {
            $response = $this->get(self::BASE . '/active-ranking', ['period' => $period], $viewer->token)->assertCode(422);
            $this->assertArrayHasKey('period', $response->data()['errors']);
            $this->assertSame(lang('validation.dashboard_period_invalid'), $response->message());
        }
    }
}
