<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线（spec §6.2、§8）：C 端流水接口只能读到当前登录用户自己的流水。`balance-logs` / `points-logs`
 * 的入参白名单里压根没有 user_id（规格 §6.2）——Controller 必须只按 ApiAuthMiddleware 设置的
 * $request->userId 取数。这条测试模拟攻击者在查询串里硬塞受害者的 user_id，断言读不到受害者的行，
 * 也不会把受害者的行计进分页总数。
 */
final class Test21_UserLogCrossUserLeakTest extends ApiTestCase
{
    public function test_balance_logs_only_return_the_caller_own_rows(): void
    {
        $victim = $this->actingAsUser();
        $attacker = $this->actingAsUser();
        $now = date('Y-m-d H:i:s');
        $this->track('balance_logs', (int) Db::table('balance_logs')->insertGetId([
            'user_id' => $victim->id, 'amount' => 100, 'before_balance' => 0, 'after_balance' => 100,
            'type' => 4, 'source' => 'admin_adjust', 'remark' => 'rl21-victim-balance', 'operator_id' => null, 'created_at' => $now,
        ]));
        $this->track('balance_logs', (int) Db::table('balance_logs')->insertGetId([
            'user_id' => $attacker->id, 'amount' => 5, 'before_balance' => 0, 'after_balance' => 5,
            'type' => 4, 'source' => 'admin_adjust', 'remark' => 'rl21-attacker-balance', 'operator_id' => null, 'created_at' => $now,
        ]));

        $response = $this->get('/api/user/balance-logs', ['page_no' => 1, 'page_size' => 50, 'user_id' => $victim->id], $attacker->token)->assertOk();
        $data = (array) $response->data();
        $remarks = array_column((array) ($data['list'] ?? []), 'remark');

        $this->assertNotContains('rl21-victim-balance', $remarks, '带上受害者的 user_id 不能读到受害者的余额流水');
        $this->assertContains('rl21-attacker-balance', $remarks, '仍然应当读到攻击者自己的流水');
        $this->assertSame(1, (int) ($data['pagination']['total'] ?? -1), 'pagination.total 必须只统计攻击者自己的行，不受伪造的 user_id 影响');
    }

    public function test_points_logs_only_return_the_caller_own_rows(): void
    {
        $victim = $this->actingAsUser();
        $attacker = $this->actingAsUser();
        $now = date('Y-m-d H:i:s');
        $this->track('points_logs', (int) Db::table('points_logs')->insertGetId([
            'user_id' => $victim->id, 'points' => 100, 'before_points' => 0, 'after_points' => 100,
            'type' => 1, 'source' => 'admin_adjust', 'remark' => 'rl21-victim-points', 'operator_id' => null, 'created_at' => $now,
        ]));
        $this->track('points_logs', (int) Db::table('points_logs')->insertGetId([
            'user_id' => $attacker->id, 'points' => 5, 'before_points' => 0, 'after_points' => 5,
            'type' => 1, 'source' => 'admin_adjust', 'remark' => 'rl21-attacker-points', 'operator_id' => null, 'created_at' => $now,
        ]));

        $response = $this->get('/api/user/points-logs', ['page_no' => 1, 'page_size' => 50, 'user_id' => $victim->id], $attacker->token)->assertOk();
        $data = (array) $response->data();
        $remarks = array_column((array) ($data['list'] ?? []), 'remark');

        $this->assertNotContains('rl21-victim-points', $remarks, '带上受害者的 user_id 不能读到受害者的积分流水');
        $this->assertContains('rl21-attacker-points', $remarks, '仍然应当读到攻击者自己的流水');
        $this->assertSame(1, (int) ($data['pagination']['total'] ?? -1), 'pagination.total 必须只统计攻击者自己的行，不受伪造的 user_id 影响');
    }
}
