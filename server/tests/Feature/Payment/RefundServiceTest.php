<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\model\user\BalanceLog;
use app\service\payment\OrderNoGenerator;
use app\service\payment\RefundService;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\exception\PaymentConfigException;
use core\payment\GatewayResolver;
use core\payment\PaymentGatewayInterface;
use support\Container;
use support\Context;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Payment\RefundFixtures;

/**
 * spec §5.6：退款两段事务夹一次网关调用。事务 1 锁订单 → 校验 → 充值单先扣余额 → 插 processing 退款单；
 * 网关成功结算、明确失败冲正、结果不确定保持 processing。
 */
final class RefundServiceTest extends ApiTestCase
{
    use RefundFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installFakeGateways();
    }

    protected function tearDown(): void
    {
        $this->restoreGatewaysAndCleanup();
        parent::tearDown();
    }

    public function test_partial_refund_debits_balance_settles_success_and_keeps_order_paid(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR001'));

        $result = $this->refundService()->refund($order['order_no'], '20.00', '用户申请', 'cli:tester');

        $this->assertSame('success', $result['status']);
        $this->assertSame('20.00', $result['amount']);
        $this->assertMatchesRegularExpression('/^F\d{22}$/', $result['refund_no'], 'F + YmdHis + 8 位随机数');
        $this->assertSame('80.00', $this->balanceOf($userId));
        $this->assertSame(
            [['amount' => '-20.00', 'type' => BalanceLog::TYPE_REFUND, 'source' => 'refund:' . $result['refund_no']]],
            $this->balanceLogsOf($userId)
        );

        $orderRow = $this->orderRow($order['id']);
        $this->assertSame('paid', $orderRow->status, '部分退款后订单仍是 paid');
        $this->assertSame(2000, (int) $orderRow->refunded_cents);

        $refund = $this->refundRow($result['refund_no']);
        $this->assertSame('success', $refund->status);
        $this->assertSame('WXR001', $refund->channel_refund_no);
        $this->assertNotNull($refund->refunded_at);
        $this->assertSame(2000, (int) $refund->amount_cents);
        $this->assertSame('cli:tester', $refund->operator);
        $this->assertSame('用户申请', $refund->reason);

        $calls = $this->wechatGateway->calls();
        $this->assertCount(1, $calls);
        $this->assertSame('refund', $calls[0]['method']);
        $request = $calls[0]['args'][0];
        $this->assertInstanceOf(RefundRequest::class, $request);
        $this->assertSame($order['order_no'], $request->orderNo);
        $this->assertSame($result['refund_no'], $request->refundNo);
        $this->assertSame(2000, $request->refundCents);
        $this->assertSame(5000, $request->totalCents);
        $this->assertSame('用户申请', $request->reason);
    }

    public function test_cumulative_refunds_reaching_the_total_mark_the_order_refunded(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-A'));
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-B'));

        $this->refundService()->refund($order['order_no'], '30.00', '', 'cli:tester');
        $this->assertSame('paid', $this->orderRow($order['id'])->status);
        $second = $this->refundService()->refund($order['order_no'], '20.00', '', 'cli:tester');

        $this->assertSame('success', $second['status']);
        $orderRow = $this->orderRow($order['id']);
        $this->assertSame('refunded', $orderRow->status, '累计退满置 refunded');
        $this->assertSame(5000, (int) $orderRow->refunded_cents);
        $this->assertSame('50.00', $this->balanceOf($userId));
        $this->assertSame(2, $this->refundCountOf($order['id']), '每次退款独立一张退款单');
    }

    public function test_refund_exceeding_the_refundable_amount_is_rejected_without_side_effects(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000, ['refunded_cents' => 3000]);

        try {
            $this->refundService()->refund($order['order_no'], '20.01', '', 'cli:tester');
            $this->fail('超额退款必须被拒绝');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.refund_exceeds'), $e->getMessage());
        }

        $this->assertSame('100.00', $this->balanceOf($userId));
        $this->assertSame([], $this->balanceLogsOf($userId));
        $this->assertSame(0, $this->refundCountOf($order['id']));
        $this->assertSame([], $this->wechatGateway->calls());
    }

    public function test_refund_is_rejected_while_another_refund_of_the_order_is_processing(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->createProcessingRefund($order['id'], 1000, date('Y-m-d H:i:s'));

        try {
            $this->refundService()->refund($order['order_no'], '10.00', '', 'cli:tester');
            $this->fail('已有处理中的退款时必须拒绝');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.refund_in_progress'), $e->getMessage());
        }

        $this->assertSame('100.00', $this->balanceOf($userId));
        $this->assertSame(1, $this->refundCountOf($order['id']));
        $this->assertSame([], $this->wechatGateway->calls());
    }

    public function test_insufficient_balance_rolls_back_everything(): void
    {
        $userId = $this->createMember('5.00');
        $order = $this->createPaidOrder($userId, 5000);

        try {
            $this->refundService()->refund($order['order_no'], '10.00', '', 'cli:tester');
            $this->fail('余额不足必须拒绝');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.refund_balance_insufficient'), $e->getMessage());
        }

        $this->assertSame('5.00', $this->balanceOf($userId));
        $this->assertSame([], $this->balanceLogsOf($userId), '余额不足时不留流水');
        $this->assertSame(0, $this->refundCountOf($order['id']), '余额不足时不留退款单');
        $this->assertSame(0, (int) $this->orderRow($order['id'])->refunded_cents);
        $this->assertSame([], $this->wechatGateway->calls(), '余额不足时不调网关');
    }

    public function test_definite_gateway_failure_marks_failed_and_reverts_the_balance(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::FAILED, null, 'NOT_ENOUGH'));

        $result = $this->refundService()->refund($order['order_no'], '20.00', '', 'cli:tester');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('100.00', $this->balanceOf($userId), '冲正后余额复原');
        $this->assertSame([
            ['amount' => '-20.00', 'type' => BalanceLog::TYPE_REFUND, 'source' => 'refund:' . $result['refund_no']],
            ['amount' => '20.00', 'type' => BalanceLog::TYPE_REFUND, 'source' => 'refund-revert:' . $result['refund_no']],
        ], $this->balanceLogsOf($userId));
        $refund = $this->refundRow($result['refund_no']);
        $this->assertSame('failed', $refund->status);
        $this->assertSame('NOT_ENOUGH', $refund->error_msg);
        $orderRow = $this->orderRow($order['id']);
        $this->assertSame('paid', $orderRow->status);
        $this->assertSame(0, (int) $orderRow->refunded_cents);
    }

    public function test_balance_log_remarks_are_chinese_regardless_of_request_locale(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::FAILED, null, 'NOT_ENOUGH'));

        Context::set('locale', 'en');
        try {
            $result = $this->refundService()->refund($order['order_no'], '20.00', '', 'cli:tester');
        } finally {
            Context::set('locale', null);
        }

        $this->assertSame('failed', $result['status']);
        $this->assertSame(
            ['充值退款', '退款失败冲正'],
            Db::table('balance_logs')->where('user_id', $userId)->orderBy('id')->pluck('remark')->all(),
            '余额流水备注是落库数据，不随请求语言变化'
        );
    }

    public function test_unknown_gateway_result_keeps_processing_without_revert(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new GatewayResultUnknownException('read timeout'));

        $result = $this->refundService()->refund($order['order_no'], '20.00', '', 'cli:tester');

        $this->assertSame('processing', $result['status']);
        $this->assertSame('processing', $this->refundRow($result['refund_no'])->status);
        $this->assertSame('80.00', $this->balanceOf($userId), '结果不确定时不冲正');
        $this->assertCount(1, $this->balanceLogsOf($userId));
        $this->assertSame(0, (int) $this->orderRow($order['id'])->refunded_cents);
    }

    public function test_processing_gateway_result_keeps_processing_without_revert(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000, ['channel' => 'alipay', 'trade_type' => 'page']);
        $this->alipayGateway->queue('refund', new RefundResult(RefundResult::PROCESSING));

        $result = $this->refundService()->refund($order['order_no'], '20.00', '', 'cli:tester');

        $this->assertSame('processing', $result['status']);
        $this->assertSame('80.00', $this->balanceOf($userId));
        $this->assertSame([], $this->wechatGateway->calls(), '按订单渠道选网关');
        $this->assertCount(1, $this->alipayGateway->calls());
    }

    public function test_settle_failed_twice_reverts_only_once(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new GatewayResultUnknownException('timeout'));
        $result = $this->refundService()->refund($order['order_no'], '20.00', '', 'cli:tester');
        $refundId = (int) $this->refundRow($result['refund_no'])->id;

        $this->refundService()->settleFailed($refundId, 'CLOSED');
        $this->refundService()->settleFailed($refundId, 'CLOSED');

        $this->assertSame('100.00', $this->balanceOf($userId));
        $this->assertCount(2, $this->balanceLogsOf($userId), '一条扣回、一条冲正，第二次结算不再冲正');
        $this->assertSame('failed', $this->refundRow($result['refund_no'])->status);
    }

    public function test_settling_a_refund_that_is_no_longer_processing_is_a_no_op(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-OK'));
        $result = $this->refundService()->refund($order['order_no'], '20.00', '', 'cli:tester');
        $refundId = (int) $this->refundRow($result['refund_no'])->id;

        $this->refundService()->settleFailed($refundId, 'late failure');
        $this->refundService()->settleSuccess($refundId, 'WXR-DUP');

        $refund = $this->refundRow($result['refund_no']);
        $this->assertSame('success', $refund->status);
        $this->assertSame('WXR-OK', $refund->channel_refund_no);
        $this->assertSame('80.00', $this->balanceOf($userId), '已成功的退款不能被冲正');
        $this->assertSame(2000, (int) $this->orderRow($order['id'])->refunded_cents, '重复结算不能重复累加');
    }

    public function test_orders_that_are_not_paid_cannot_be_refunded(): void
    {
        $userId = $this->createMember('100.00');
        foreach (['pending', 'closed', 'refunded'] as $status) {
            $order = $this->createPaidOrder($userId, 5000, ['status' => $status, 'refunded_cents' => $status === 'refunded' ? 5000 : 0]);
            try {
                $this->refundService()->refund($order['order_no'], '1.00', '', 'cli:tester');
                $this->fail("{$status} 订单不能退款");
            } catch (BusinessException $e) {
                $this->assertSame(lang('payment.refund_status_invalid'), $e->getMessage(), $status);
            }
            $this->assertSame(0, $this->refundCountOf($order['id']), $status);
        }
        $this->assertSame('100.00', $this->balanceOf($userId));
        $this->assertSame([], $this->wechatGateway->calls());
    }

    public function test_unknown_order_is_not_found(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(lang('payment.order_not_found'));

        $this->refundService()->refund('R-NOT-EXIST-' . bin2hex(random_bytes(4)), '1.00', '', 'cli:tester');
    }

    public function test_incomplete_credentials_fail_before_the_balance_is_touched(): void
    {
        $this->installFakeGateways(new class () implements GatewayResolver {
            public function isEnabled(string $channel): bool
            {
                return false;
            }

            public function gateway(string $channel): PaymentGatewayInterface
            {
                throw new PaymentConfigException('pay_wechat_mch_id missing');
            }
        });
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);

        try {
            $this->refundService()->refund($order['order_no'], '10.00', '', 'cli:tester');
            $this->fail('凭据不全必须拒绝');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.unavailable'), $e->getMessage(), '对外不暴露缺哪项配置');
        }

        $this->assertSame('100.00', $this->balanceOf($userId));
        $this->assertSame([], $this->balanceLogsOf($userId));
        $this->assertSame(0, $this->refundCountOf($order['id']));
    }

    public function test_non_recharge_orders_do_not_touch_the_balance(): void
    {
        $userId = $this->createMember('100.00');
        $success = $this->createPaidOrder($userId, 5000, ['biz_type' => 'fixture_biz']);
        $failed = $this->createPaidOrder($userId, 5000, ['biz_type' => 'fixture_biz']);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-S'));
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::FAILED, null, 'CLOSED'));

        $this->assertSame('success', $this->refundService()->refund($success['order_no'], '10.00', '', 'cli:tester')['status']);
        $this->assertSame('failed', $this->refundService()->refund($failed['order_no'], '10.00', '', 'cli:tester')['status']);

        $this->assertSame('100.00', $this->balanceOf($userId));
        $this->assertSame([], $this->balanceLogsOf($userId), '只有充值单扣余额与冲正');
    }

    public function test_refund_no_collision_retries_the_whole_first_transaction(): void
    {
        $userId = $this->createMember('100.00');
        $other = $this->createPaidOrder($userId, 5000);
        $taken = $this->createProcessingRefund($other['id'], 100, date('Y-m-d H:i:s'))['refund_no'];
        $order = $this->createPaidOrder($userId, 5000);
        $generator = new class ($taken) extends OrderNoGenerator {
            /** @var list<string> */
            public array $numbers;

            public function __construct(string $taken)
            {
                $this->numbers = [$taken, 'F2026091600000000000009'];
            }

            public function generate(string $prefix): string
            {
                return (string) array_shift($this->numbers);
            }
        };
        $original = Container::get(OrderNoGenerator::class);
        Container::set(OrderNoGenerator::class, $generator);
        Container::set(RefundService::class, Container::make(RefundService::class, []));
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-C'));

        try {
            $result = $this->refundService()->refund($order['order_no'], '10.00', '', 'cli:tester');
        } finally {
            Container::set(OrderNoGenerator::class, $original);
            Container::set(RefundService::class, Container::make(RefundService::class, []));
        }

        $this->assertSame('F2026091600000000000009', $result['refund_no']);
        $this->assertSame([], $generator->numbers);
        // 撞号那一次的扣款必须随事务 1 整体回滚：只剩换号成功后的那一条流水
        $this->assertSame('90.00', $this->balanceOf($userId));
        $this->assertCount(1, $this->balanceLogsOf($userId));
    }

    public function test_reason_is_truncated_to_80_bytes_without_breaking_characters(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-R'));

        $result = $this->refundService()->refund($order['order_no'], '1.00', str_repeat('退', 40), 'cli:tester');

        $reason = (string) $this->refundRow($result['refund_no'])->reason;
        $this->assertSame(str_repeat('退', 26), $reason, '80 字节按字节截断，不切断多字节字符（26×3=78）');
        $this->assertSame($reason, $this->wechatGateway->calls()[0]['args'][0]->reason);
    }
}
