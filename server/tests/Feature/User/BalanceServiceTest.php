<?php

declare(strict_types=1);

namespace tests\Feature\User;

use app\model\user\BalanceLog;
use app\service\user\BalanceService;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

/**
 * spec §5.1：余额变动的唯一写入口。流水的 before_balance / after_balance 取自锁内读到的值，
 * 结果为负一律 422（errors.amount），被拒绝时余额不动、也不留流水。
 */
final class BalanceServiceTest extends ApiTestCase
{
    /** @var list<int> 本用例建的用户：流水行按 user_id 清（balance_logs 没进 track()） */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            Db::table('balance_logs')->whereIn('user_id', $this->userIds)->delete();
        }
        $this->userIds = [];
        parent::tearDown();
    }

    private function createUser(string $balance = '100.00'): int
    {
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $id = (int) Db::table('users')->insertGetId([
            'nickname'   => "u_{$suffix}",
            'mobile'     => '19' . str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT),
            'password'   => password_hash('Passw0rd!', PASSWORD_DEFAULT),
            'status'     => 1,
            'balance'    => $balance,
            'points'     => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->track('users', $id);
        $this->userIds[] = $id;

        return $id;
    }

    private function service(): BalanceService
    {
        return Container::get(BalanceService::class);
    }

    public function test_change_updates_the_balance_and_writes_one_ledger_row(): void
    {
        $userId = $this->createUser('100.00');

        $result = $this->service()->change($userId, 25.50, BalanceLog::TYPE_RECHARGE, 'recharge', '充值测试');

        $this->assertSame(['before' => 100.00, 'after' => 125.50], $result);
        $this->assertSame('125.50', (string) Db::table('users')->where('id', $userId)->value('balance'));

        $logs = Db::table('balance_logs')->where('user_id', $userId)->get()->all();
        $this->assertCount(1, $logs);
        $this->assertSame('25.50', (string) $logs[0]->amount);
        $this->assertSame('100.00', (string) $logs[0]->before_balance);
        $this->assertSame('125.50', (string) $logs[0]->after_balance);
        $this->assertSame(BalanceLog::TYPE_RECHARGE, (int) $logs[0]->type);
        $this->assertSame('recharge', (string) $logs[0]->source);
        $this->assertSame('充值测试', (string) $logs[0]->remark);
        $this->assertNull($logs[0]->operator_id, '用户自己触发的变动没有操作人');
        $this->assertNotNull($logs[0]->created_at);
    }

    public function test_deduction_records_the_operator_and_keeps_two_decimals(): void
    {
        $userId = $this->createUser('0.30');

        $result = $this->service()->change($userId, -0.10, BalanceLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '后台调减', 42);

        $this->assertSame(['before' => 0.30, 'after' => 0.20], $result, '0.3 - 0.1 必须正好是 0.20：金额按分做整数加减，不能拿浮点数直接相减');
        $this->assertSame('0.20', (string) Db::table('users')->where('id', $userId)->value('balance'));
        $log = Db::table('balance_logs')->where('user_id', $userId)->first();
        $this->assertSame('-0.10', (string) $log->amount);
        $this->assertSame(42, (int) $log->operator_id);
        $this->assertSame(BalanceLog::TYPE_ADMIN_ADJUST, (int) $log->type);
    }

    public function test_a_negative_result_is_rejected_and_nothing_is_written(): void
    {
        $userId = $this->createUser('10.00');

        try {
            $this->service()->change($userId, -10.01, BalanceLog::TYPE_CONSUME, 'consume');
            $this->fail('扣成负数必须抛 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame(['amount' => lang('validation.balance_not_enough')], $e->errors());
        }

        $this->assertSame('10.00', (string) Db::table('users')->where('id', $userId)->value('balance'), '被拒绝时余额不动');
        $this->assertSame(0, Db::table('balance_logs')->where('user_id', $userId)->count(), '被拒绝时不留流水');
    }

    public function test_deducting_down_to_exactly_zero_is_allowed(): void
    {
        $userId = $this->createUser('10.00');

        $this->assertSame(['before' => 10.00, 'after' => 0.00], $this->service()->change($userId, -10.00, BalanceLog::TYPE_CONSUME, 'consume'));
        $this->assertSame('0.00', (string) Db::table('users')->where('id', $userId)->value('balance'));
    }

    public function test_unknown_user_is_404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service()->change(999_999_999, 1.00, BalanceLog::TYPE_RECHARGE, 'recharge');
    }
}
