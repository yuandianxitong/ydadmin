<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\model\payment\RefundOrder;
use app\repository\payment\RefundOrderRepository;
use support\Db;
use tests\TestCase;

final class RefundOrderRepositoryTest extends TestCase
{
    /** @var list<int> */
    private array $refundIds = [];

    protected function tearDown(): void
    {
        if ($this->refundIds !== []) {
            Db::table('refund_orders')->whereIn('id', $this->refundIds)->delete();
        }
        $this->refundIds = [];
        Db::connection()->disableQueryLog();
        Db::connection()->flushQueryLog();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function refund(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('refund_orders')->insertGetId(array_merge([
            'refund_no'        => 'FTEST' . bin2hex(random_bytes(8)),
            'payment_order_id' => 900001,
            'amount_cents'     => 500,
            'reason'           => '',
            'status'           => 'processing',
            'operator'         => 'cli:tester',
            'created_at'       => $now,
            'updated_at'       => $now,
        ], $attributes));
        $this->refundIds[] = $id;

        return $id;
    }

    public function test_forwarded_constants_match_model(): void
    {
        $this->assertSame(RefundOrder::STATUS_PROCESSING, RefundOrderRepository::STATUS_PROCESSING);
        $this->assertSame(RefundOrder::STATUS_SUCCESS, RefundOrderRepository::STATUS_SUCCESS);
        $this->assertSame(RefundOrder::STATUS_FAILED, RefundOrderRepository::STATUS_FAILED);
        $this->assertSame(['processing', 'success', 'failed'], [
            RefundOrder::STATUS_PROCESSING, RefundOrder::STATUS_SUCCESS, RefundOrder::STATUS_FAILED,
        ]);
    }

    public function test_create_and_find_by_refund_no_with_integer_casts(): void
    {
        $refundNo = 'FTEST' . bin2hex(random_bytes(8));
        $row = (new RefundOrderRepository())->create([
            'refund_no'        => $refundNo,
            'payment_order_id' => 900002,
            'amount_cents'     => 250,
            'reason'           => '用户申请',
            'status'           => 'processing',
            'operator'         => 'cli:tester',
        ]);
        $this->refundIds[] = (int) $row['id'];

        $found = (new RefundOrderRepository())->findByRefundNo($refundNo);
        $this->assertNotNull($found);
        $this->assertSame(900002, $found['payment_order_id']);
        $this->assertSame(250, $found['amount_cents']);
        $this->assertSame('processing', $found['status']);
        $this->assertNull($found['refunded_at']);
        $this->assertNull((new RefundOrderRepository())->findByRefundNo('FNOTEXIST' . bin2hex(random_bytes(6))));
    }

    public function test_find_for_update_locks_the_row(): void
    {
        $id = $this->refund();

        Db::connection()->enableQueryLog();
        Db::connection()->flushQueryLog();
        $row = Db::connection()->transaction(fn () => (new RefundOrderRepository())->findForUpdate($id));

        $this->assertSame($id, $row['id']);
        $log = Db::connection()->getQueryLog();
        $this->assertStringContainsString('for update', strtolower((string) end($log)['query']));
        $this->assertNull((new RefundOrderRepository())->findForUpdate(PHP_INT_MAX));
    }

    public function test_has_processing_only_counts_processing_refunds_of_that_order(): void
    {
        $this->refund(['payment_order_id' => 900010, 'status' => 'success']);
        $this->refund(['payment_order_id' => 900010, 'status' => 'failed']);
        $this->refund(['payment_order_id' => 900011, 'status' => 'processing']);

        $this->assertFalse((new RefundOrderRepository())->hasProcessing(900010));
        $this->assertTrue((new RefundOrderRepository())->hasProcessing(900011));

        $this->refund(['payment_order_id' => 900010, 'status' => 'processing']);
        $this->assertTrue((new RefundOrderRepository())->hasProcessing(900010));
    }

    public function test_find_processing_created_before_filters_and_orders(): void
    {
        $late = $this->refund(['created_at' => '2000-01-01 10:00:02']);
        $early = $this->refund(['created_at' => '2000-01-01 10:00:01']);
        $sameA = $this->refund(['created_at' => '2000-01-01 10:00:03']);
        $sameB = $this->refund(['created_at' => '2000-01-01 10:00:03']);
        $this->refund(['created_at' => '2000-01-01 10:00:01', 'status' => 'success']);
        $this->refund(['created_at' => '2000-01-01 10:00:01', 'status' => 'failed']);
        $this->refund(['created_at' => '2000-01-01 11:00:00']);

        $rows = (new RefundOrderRepository())->findProcessingCreatedBefore(new \DateTimeImmutable('2000-01-01 11:00:00'), 100);

        $this->assertSame([$early, $late, $sameA, $sameB], array_column($rows, 'id'));
        $this->assertSame(array_keys($rows), range(0, count($rows) - 1), '返回 list');
    }

    public function test_find_processing_created_before_respects_limit(): void
    {
        $first = $this->refund(['created_at' => '2000-01-02 10:00:01']);
        $this->refund(['created_at' => '2000-01-02 10:00:02']);

        $rows = (new RefundOrderRepository())->findProcessingCreatedBefore(new \DateTimeImmutable('2000-01-02 11:00:00'), 1);

        $this->assertSame([$first], array_column($rows, 'id'));
    }
}
