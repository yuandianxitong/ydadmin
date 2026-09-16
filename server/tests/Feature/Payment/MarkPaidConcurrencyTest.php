<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\service\payment\MarkPaidOutcome;
use app\service\payment\PaymentService;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConcurrentWorkers;

/**
 * spec §10.2「两个回调并发只入账一次」：两个独立进程同时 markPaid。
 * markPaid 不碰网关，这里用真实容器里的 PaymentService，不需要假网关。
 */
final class MarkPaidConcurrencyTest extends ApiTestCase
{
    use ConcurrentWorkers;

    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            Db::table('payment_orders')->whereIn('user_id', $this->userIds)->delete();
            Db::table('balance_logs')->whereIn('user_id', $this->userIds)->delete();
        }
        $this->userIds = [];
        $this->cleanupConcurrencyFiles();
        parent::tearDown();
    }

    private function createUser(): int
    {
        $id = $this->actingAsUser(['balance' => '0.00'])->id;
        $this->userIds[] = $id;

        return $id;
    }

    private function insertOrder(int $userId, string $orderNo, int $cents): void
    {
        $now = date('Y-m-d H:i:s');
        Db::table('payment_orders')->insert([
            'user_id' => $userId, 'biz_type' => 'recharge', 'client_type' => 'pc', 'order_no' => $orderNo,
            'channel' => 'wechat', 'trade_type' => 'native', 'subject' => '余额充值', 'amount_cents' => $cents,
            'status' => 'pending', 'expires_at' => date('Y-m-d H:i:s', time() + 1800), 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_duplicate_notifications_racing_credit_exactly_once(): void
    {
        $userId = $this->createUser();
        $orderNo = 'R' . date('YmdHis') . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        $this->insertOrder($userId, $orderNo, 8800);

        $results = $this->runConcurrently(2, static function () use ($orderNo): array {
            return ['outcome' => Container::get(PaymentService::class)->markPaid($orderNo, 'wechat', '4200000000000000100', 8800, [])];
        });

        $this->assertWorkersOverlapped($results);
        $outcomes = array_column($results, 'outcome');
        sort($outcomes);
        $this->assertSame([MarkPaidOutcome::ALREADY, MarkPaidOutcome::PAID], $outcomes, '一个进程入账，另一个在行锁后看到已支付');
        $this->assertSame(1, Db::table('balance_logs')->where('user_id', $userId)->count(), '只能有一条充值流水');
        $this->assertSame('88.00', (string) Db::table('users')->where('id', $userId)->value('balance'));
    }

    public function test_different_orders_of_the_same_user_credit_without_lost_updates(): void
    {
        $userId = $this->createUser();
        $orderNos = [[], []];
        for ($worker = 0; $worker < 2; $worker++) {
            for ($i = 0; $i < 10; $i++) {
                $orderNo = sprintf('R%s%d%07d', date('YmdHis'), $worker, $i + random_int(0, 9_990_000));
                $this->insertOrder($userId, $orderNo, 100);
                $orderNos[$worker][] = $orderNo;
            }
        }

        $results = $this->runConcurrently(2, static function (int $index) use ($orderNos): array {
            $paid = 0;
            foreach ($orderNos[$index] as $orderNo) {
                if (Container::get(PaymentService::class)->markPaid($orderNo, 'wechat', 't-' . $orderNo, 100, []) === MarkPaidOutcome::PAID) {
                    $paid++;
                }
            }

            return ['paid' => $paid];
        });

        $this->assertWorkersOverlapped($results);
        $this->assertSame([10, 10], array_column($results, 'paid'));
        $logs = Db::table('balance_logs')->where('user_id', $userId)->orderBy('id')->get()->all();
        $this->assertCount(20, $logs);
        $expected = '0.00';
        foreach ($logs as $log) {
            $this->assertSame($expected, (string) $log->before_balance, '行锁顺序「订单 → 用户」下，同一用户的入账仍然串行，流水前后相接');
            $expected = (string) $log->after_balance;
        }
        $this->assertSame('20.00', $expected);
        $this->assertSame('20.00', (string) Db::table('users')->where('id', $userId)->value('balance'));
    }
}
