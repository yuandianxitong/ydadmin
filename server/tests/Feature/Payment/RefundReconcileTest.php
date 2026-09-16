<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\model\user\BalanceLog;
use core\payment\dto\RefundResult;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\exception\PaymentConfigException;
use core\payment\GatewayResolver;
use core\payment\PaymentGatewayInterface;
use Monolog\Handler\TestHandler;
use support\Log;
use tests\Support\ApiTestCase;
use tests\Support\Payment\RefundFixtures;

/**
 * spec §5.7：扫描创建超过 2 分钟的 processing 退款单逐条查网关。成功/失败按 Task 11 同样的结算处理；
 * 渠道查无此退款时满 30 分钟才判失败并冲正；超过 24 小时仍在处理记 error 日志；每条独立 try/catch。
 *
 * 夹具插的 processing 退款单不经事务 1，不会自动扣余额：用例把会员余额直接设成「扣过之后」的值。
 * 日志经 Monolog TestHandler 断言（support\Log 的静态门面转发到 channel('default')）。
 */
final class RefundReconcileTest extends ApiTestCase
{
    use RefundFixtures;

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installFakeGateways();
        $this->logs = new TestHandler();
        Log::channel()->pushHandler($this->logs);
    }

    protected function tearDown(): void
    {
        Log::channel()->popHandler();
        $this->restoreGatewaysAndCleanup();
        parent::tearDown();
    }

    private static function ago(\DateTimeImmutable $now, string $modifier): string
    {
        return $now->modify($modifier)->format('Y-m-d H:i:s');
    }

    public function test_success_result_settles_the_refund_and_the_order(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $refund = $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-5 minutes'));
        $this->wechatGateway->queue('queryRefund', new RefundResult(RefundResult::SUCCESS, 'WXR-REC'));

        $counts = $this->refundService()->reconcile($now);

        $this->assertSame(['scanned' => 1, 'success' => 1, 'failed' => 0, 'skipped' => 0], $counts);
        $row = $this->refundRow($refund['refund_no']);
        $this->assertSame('success', $row->status);
        $this->assertSame('WXR-REC', $row->channel_refund_no);
        $this->assertSame(2000, (int) $this->orderRow($order['id'])->refunded_cents);
        $this->assertSame('80.00', $this->balanceOf($userId));
        $calls = $this->wechatGateway->calls();
        $this->assertSame('queryRefund', $calls[0]['method']);
        $this->assertSame([$order['order_no'], $refund['refund_no']], $calls[0]['args']);
    }

    public function test_failed_result_marks_failed_and_reverts_the_balance(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $refund = $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-5 minutes'));
        $this->wechatGateway->queue('queryRefund', new RefundResult(RefundResult::FAILED, null, 'CLOSED'));

        $counts = $this->refundService()->reconcile($now);

        $this->assertSame(['scanned' => 1, 'success' => 0, 'failed' => 1, 'skipped' => 0], $counts);
        $this->assertSame('failed', $this->refundRow($refund['refund_no'])->status);
        $this->assertSame('100.00', $this->balanceOf($userId));
        $this->assertSame(
            [['amount' => '20.00', 'type' => BalanceLog::TYPE_REFUND, 'source' => 'refund-revert:' . $refund['refund_no']]],
            $this->balanceLogsOf($userId)
        );
    }

    public function test_not_found_younger_than_thirty_minutes_is_skipped(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $refund = $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-10 minutes'));
        $this->wechatGateway->queue('queryRefund', new RefundResult(RefundResult::NOT_FOUND));

        $counts = $this->refundService()->reconcile($now);

        $this->assertSame(['scanned' => 1, 'success' => 0, 'failed' => 0, 'skipped' => 1], $counts);
        $this->assertSame('processing', $this->refundRow($refund['refund_no'])->status, '请求可能还在途中');
        $this->assertSame('80.00', $this->balanceOf($userId));
    }

    public function test_not_found_older_than_thirty_minutes_fails_and_reverts(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000, ['channel' => 'alipay', 'trade_type' => 'page']);
        $refund = $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-31 minutes'));
        $this->alipayGateway->queue('queryRefund', new RefundResult(RefundResult::NOT_FOUND));

        $counts = $this->refundService()->reconcile($now);

        $this->assertSame(['scanned' => 1, 'success' => 0, 'failed' => 1, 'skipped' => 0], $counts);
        $row = $this->refundRow($refund['refund_no']);
        $this->assertSame('failed', $row->status);
        $this->assertSame('channel_refund_not_found', $row->error_msg);
        $this->assertSame('100.00', $this->balanceOf($userId));
    }

    public function test_processing_result_is_skipped(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $refund = $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-5 minutes'));
        $this->wechatGateway->queue('queryRefund', new RefundResult(RefundResult::PROCESSING));

        $this->assertSame(['scanned' => 1, 'success' => 0, 'failed' => 0, 'skipped' => 1], $this->refundService()->reconcile($now));
        $this->assertSame('processing', $this->refundRow($refund['refund_no'])->status);
    }

    public function test_refunds_younger_than_two_minutes_are_not_scanned(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-60 seconds'));

        $this->assertSame(['scanned' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0], $this->refundService()->reconcile($now));
        $this->assertSame([], $this->wechatGateway->calls());
    }

    public function test_one_failing_row_does_not_stop_the_batch(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('60.00');
        $broken = $this->createPaidOrder($userId, 5000);
        $healthy = $this->createPaidOrder($userId, 5000, ['channel' => 'alipay', 'trade_type' => 'page']);
        $brokenRefund = $this->createProcessingRefund($broken['id'], 2000, self::ago($now, '-10 minutes'));
        $healthyRefund = $this->createProcessingRefund($healthy['id'], 2000, self::ago($now, '-5 minutes'));
        $this->wechatGateway->queue('queryRefund', new GatewayResultUnknownException('read timeout'));
        $this->alipayGateway->queue('queryRefund', new RefundResult(RefundResult::SUCCESS, 'ALI-REC'));

        $counts = $this->refundService()->reconcile($now);

        $this->assertSame(['scanned' => 2, 'success' => 1, 'failed' => 0, 'skipped' => 1], $counts);
        $this->assertSame('processing', $this->refundRow($brokenRefund['refund_no'])->status);
        $this->assertSame('success', $this->refundRow($healthyRefund['refund_no'])->status);
        $this->assertTrue($this->logs->hasWarningThatContains('退款对账单条失败'));
    }

    public function test_refund_processing_for_more_than_a_day_logs_an_error(): void
    {
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $refund = $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-25 hours'));
        $this->wechatGateway->queue('queryRefund', new RefundResult(RefundResult::PROCESSING));

        $counts = $this->refundService()->reconcile($now);

        $this->assertSame(['scanned' => 1, 'success' => 0, 'failed' => 0, 'skipped' => 1], $counts);
        $this->assertTrue($this->logs->hasErrorThatContains('退款长时间处理中'), '超过 24 小时需要人工介入');
        $this->assertSame('processing', $this->refundRow($refund['refund_no'])->status);
    }

    public function test_incomplete_credentials_skip_the_row(): void
    {
        $this->installFakeGateways(new class () implements GatewayResolver {
            public function isEnabled(string $channel): bool
            {
                return true;
            }

            public function gateway(string $channel): PaymentGatewayInterface
            {
                throw new PaymentConfigException('pay_wechat_api_v3_key missing');
            }
        });
        $now = new \DateTimeImmutable();
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $refund = $this->createProcessingRefund($order['id'], 2000, self::ago($now, '-5 minutes'));

        $this->assertSame(['scanned' => 1, 'success' => 0, 'failed' => 0, 'skipped' => 1], $this->refundService()->reconcile($now));
        $this->assertSame('processing', $this->refundRow($refund['refund_no'])->status);
        $this->assertSame('80.00', $this->balanceOf($userId));
    }
}
