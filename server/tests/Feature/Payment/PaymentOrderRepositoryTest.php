<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\model\payment\PaymentOrder;
use app\repository\payment\PaymentOrderRepository;
use support\Db;
use tests\TestCase;

final class PaymentOrderRepositoryTest extends TestCase
{
    /** @var list<int> */
    private array $orderIds = [];

    protected function tearDown(): void
    {
        if ($this->orderIds !== []) {
            Db::table('payment_orders')->whereIn('id', $this->orderIds)->delete();
        }
        $this->orderIds = [];
        Db::connection()->disableQueryLog();
        Db::connection()->flushQueryLog();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function order(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('payment_orders')->insertGetId(array_merge([
            'user_id'      => 1,
            'biz_type'     => 'recharge',
            'client_type'  => 'pc',
            'order_no'     => 'RTEST' . bin2hex(random_bytes(8)),
            'channel'      => 'wechat',
            'trade_type'   => 'native',
            'subject'      => '余额充值',
            'amount_cents' => 1000,
            'status'       => 'pending',
            'expires_at'   => date('Y-m-d H:i:s', time() + 1800),
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $attributes));
        $this->orderIds[] = $id;

        return $id;
    }

    private function lastSql(): string
    {
        $log = Db::connection()->getQueryLog();
        $this->assertNotSame([], $log, '没有记录到查询');

        return strtolower((string) end($log)['query']);
    }

    public function test_forwarded_constants_match_model(): void
    {
        $this->assertSame(PaymentOrder::STATUS_PENDING, PaymentOrderRepository::STATUS_PENDING);
        $this->assertSame(PaymentOrder::STATUS_PAID, PaymentOrderRepository::STATUS_PAID);
        $this->assertSame(PaymentOrder::STATUS_CLOSED, PaymentOrderRepository::STATUS_CLOSED);
        $this->assertSame(PaymentOrder::STATUS_REFUNDED, PaymentOrderRepository::STATUS_REFUNDED);
        $this->assertSame(PaymentOrder::BIZ_RECHARGE, PaymentOrderRepository::BIZ_RECHARGE);
        $this->assertSame(['pending', 'paid', 'closed', 'refunded', 'recharge'], [
            PaymentOrder::STATUS_PENDING, PaymentOrder::STATUS_PAID, PaymentOrder::STATUS_CLOSED,
            PaymentOrder::STATUS_REFUNDED, PaymentOrder::BIZ_RECHARGE,
        ]);
    }

    public function test_create_returns_row_with_integer_casts_and_json_notify_data(): void
    {
        $orderNo = 'RTEST' . bin2hex(random_bytes(8));
        $row = (new PaymentOrderRepository())->create([
            'user_id'      => 7,
            'biz_type'     => 'recharge',
            'client_type'  => 'h5',
            'order_no'     => $orderNo,
            'channel'      => 'alipay',
            'trade_type'   => 'wap',
            'subject'      => '余额充值',
            'amount_cents' => 1234,
            'status'       => 'pending',
            'expires_at'   => '2026-09-16 12:30:00',
        ]);
        $this->orderIds[] = (int) $row['id'];

        $found = (new PaymentOrderRepository())->findByOrderNo($orderNo);
        $this->assertNotNull($found);
        $this->assertSame(7, $found['user_id']);
        $this->assertSame(1234, $found['amount_cents']);
        $this->assertSame(0, $found['refunded_cents']);
        $this->assertSame('pending', $found['status']);
        $this->assertSame('2026-09-16 12:30:00', $found['expires_at']);
        $this->assertNull($found['notify_data']);

        (new PaymentOrderRepository())->update((int) $row['id'], ['notify_data' => ['trade_state' => 'SUCCESS', 'amount' => ['total' => 1234]]]);
        $updated = (new PaymentOrderRepository())->findByOrderNo($orderNo);
        // 内容比较，不比较键顺序：MySQL 8 的 JSON 二进制存储按键长重排对象成员（文档化行为），
        // 取回的键序与写入时不同（'amount' 6 字符排在 'trade_state' 11 字符之前）。
        $keys = array_keys($updated['notify_data']);
        sort($keys);
        $this->assertSame(['amount', 'trade_state'], $keys);
        $this->assertSame('SUCCESS', $updated['notify_data']['trade_state']);
        $this->assertSame(1234, $updated['notify_data']['amount']['total']);
    }

    public function test_find_by_order_no_returns_null_when_missing(): void
    {
        $this->assertNull((new PaymentOrderRepository())->findByOrderNo('RNOTEXIST' . bin2hex(random_bytes(6))));
    }

    public function test_find_by_order_no_for_update_locks_the_row(): void
    {
        $id = $this->order(['order_no' => $orderNo = 'RLOCK' . bin2hex(random_bytes(8))]);

        Db::connection()->enableQueryLog();
        Db::connection()->flushQueryLog();
        $row = Db::connection()->transaction(fn () => (new PaymentOrderRepository())->findByOrderNoForUpdate($orderNo));

        $this->assertSame($id, $row['id']);
        $this->assertStringContainsString('for update', $this->lastSql());
        $this->assertNull((new PaymentOrderRepository())->findByOrderNoForUpdate('RNOTEXIST' . bin2hex(random_bytes(6))));
    }

    public function test_find_for_update_locks_the_row_by_id(): void
    {
        $id = $this->order();

        Db::connection()->enableQueryLog();
        Db::connection()->flushQueryLog();
        $row = Db::connection()->transaction(fn () => (new PaymentOrderRepository())->findForUpdate($id));

        $this->assertSame($id, $row['id']);
        $this->assertStringContainsString('for update', $this->lastSql());
        $this->assertNull((new PaymentOrderRepository())->findForUpdate(PHP_INT_MAX));
    }

    public function test_find_for_user_only_returns_own_order(): void
    {
        $orderNo = 'RUSER' . bin2hex(random_bytes(8));
        $id = $this->order(['order_no' => $orderNo, 'user_id' => 101]);

        $mine = (new PaymentOrderRepository())->findForUser($orderNo, 101);
        $this->assertNotNull($mine);
        $this->assertSame($id, $mine['id']);
        $this->assertNull((new PaymentOrderRepository())->findForUser($orderNo, 102));
        $this->assertNull((new PaymentOrderRepository())->findForUser('RNOTEXIST' . bin2hex(random_bytes(6)), 101));
    }

    public function test_find_expired_pending_filters_status_and_deadline_and_orders_by_expiry(): void
    {
        // 用 2000 年的截止时间：测试库里其它用例留下的行都是「现在」附近，不会混进来
        $late = $this->order(['expires_at' => '2000-01-01 10:00:02']);
        $early = $this->order(['expires_at' => '2000-01-01 10:00:01']);
        $sameTimeA = $this->order(['expires_at' => '2000-01-01 10:00:03']);
        $sameTimeB = $this->order(['expires_at' => '2000-01-01 10:00:03']);
        $this->order(['expires_at' => '2000-01-01 10:00:01', 'status' => 'paid']);
        $this->order(['expires_at' => '2000-01-01 10:00:01', 'status' => 'closed']);
        $this->order(['expires_at' => '2000-01-01 11:00:00']); // 截止时间不早于 $before

        $rows = (new PaymentOrderRepository())->findExpiredPending(new \DateTimeImmutable('2000-01-01 11:00:00'), 100);

        $this->assertSame([$early, $late, $sameTimeA, $sameTimeB], array_column($rows, 'id'));
        $this->assertSame(array_keys($rows), range(0, count($rows) - 1), '返回 list');
    }

    public function test_find_expired_pending_respects_limit(): void
    {
        $first = $this->order(['expires_at' => '2000-01-02 10:00:01']);
        $second = $this->order(['expires_at' => '2000-01-02 10:00:02']);
        $this->order(['expires_at' => '2000-01-02 10:00:03']);

        $rows = (new PaymentOrderRepository())->findExpiredPending(new \DateTimeImmutable('2000-01-02 11:00:00'), 2);

        $this->assertSame([$first, $second], array_column($rows, 'id'));
    }
}
