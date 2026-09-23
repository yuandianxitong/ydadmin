<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\adminapi\controller\payment\PaymentOrderController;
use app\service\payment\PaymentAdminService;
use core\payment\dto\RefundResult;
use support\Container;
use tests\Support\ApiTestCase;
use tests\Support\Payment\RefundFixtures;

final class PaymentOrderAdminApiTest extends ApiTestCase
{
    use RefundFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installFakeGateways();
        Container::set(PaymentAdminService::class, Container::make(PaymentAdminService::class));
        Container::set(PaymentOrderController::class, Container::make(PaymentOrderController::class));
    }

    protected function tearDown(): void
    {
        $this->restoreGatewaysAndCleanup();
        parent::tearDown();
    }

    public function test_list_and_detail_hide_notify_data_and_use_yuan(): void
    {
        $admin = $this->actingAsAdmin(['payment.order.list', 'payment.order.detail']);
        $userId = $this->createMember('50.00');
        $order = $this->createPaidOrder($userId, 1234, ['notify_data' => json_encode(['secret' => 'hide-me'], JSON_THROW_ON_ERROR)]);

        $list = $this->get('/adminapi/payment/order/list', ['order_no' => $order['order_no']], $admin->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($list));
        $this->assertCount(1, $list['list']);
        $row = $list['list'][0];
        $this->assertSame('12.34', $row['amount']);
        $this->assertSame('0.00', $row['refunded_amount']);
        $this->assertArrayNotHasKey('notify_data', $row);
        $this->assertArrayNotHasKey('amount_cents', $row);

        $detail = $this->get('/adminapi/payment/order/' . $order['order_no'], [], $admin->token)->assertOk()->data();
        $this->assertSame($order['order_no'], $detail['order_no']);
        $this->assertSame('12.34', $detail['amount']);
        $this->assertArrayNotHasKey('notify_data', $detail);
        $this->assertSame([], $detail['refunds']);
        $this->assertStringNotContainsString('hide-me', $this->get('/adminapi/payment/order/' . $order['order_no'], [], $admin->token)->body());
    }

    public function test_admin_refund_uses_refund_service(): void
    {
        $admin = $this->actingAsAdmin(['payment.order.refund', 'payment.order.detail']);
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-ADMIN'));

        $result = $this->post('/adminapi/payment/order/refund', [
            'order_no' => $order['order_no'],
            'amount'   => '20.00',
            'reason'   => '后台退款',
        ], $admin->token)->assertOk()->data();

        $this->assertSame('success', $result['status']);
        $this->assertSame('20.00', $result['amount']);
        $detail = $this->get('/adminapi/payment/order/' . $order['order_no'], [], $admin->token)->assertOk()->data();
        $this->assertSame('20.00', $detail['refunded_amount']);
        $this->assertSame('admin:' . $admin->id, $detail['refunds'][0]['operator']);
    }

    public function test_refund_requires_permission(): void
    {
        $admin = $this->actingAsAdmin(['payment.order.list']);
        $this->post('/adminapi/payment/order/refund', [
            'order_no' => 'RT000',
            'amount'   => '1.00',
        ], $admin->token)->assertCode(403);
    }
}
