<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\service\payment\PaymentService;
use core\payment\dto\NotifyResult;
use core\payment\GatewayResolver;
use core\payment\PaymentManager;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;

/**
 * spec §5.3 / §7.1：两个公开回调端点。不带 token、不走统一响应体，应答形状由渠道决定。
 * 测试库里 payment 组配置是空的（Task 6 种子默认值），所以不换解析器时取网关必然失败 → 走兜底失败应答。
 */
final class PaymentNotifyApiTest extends ApiTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        Container::set(GatewayResolver::class, Container::get(PaymentManager::class));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
        if ($this->userIds !== []) {
            Db::table('balance_logs')->whereIn('user_id', $this->userIds)->delete();
        }
        $this->userIds = [];
        parent::tearDown();
    }

    private function useFakeGateway(FakeGateway $wechat): void
    {
        Container::set(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $wechat, 'alipay' => new FakeGateway()]));
        // 控制器每次请求现 make（controller_reuse=false），但它注入的 PaymentService 是容器单例，得一并换掉
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
    }

    public function test_wechat_notify_without_credentials_answers_500_json_fail(): void
    {
        $response = $this->postRaw('/api/payment/notify/wechat', '{"id":"EV-1","resource":{}}', [
            'Content-Type'        => 'application/json',
            'Wechatpay-Timestamp' => (string) time(),
            'Wechatpay-Nonce'     => 'nonce',
            'Wechatpay-Signature' => 'bogus',
            'Wechatpay-Serial'    => 'SERIAL',
        ]);

        $this->assertSame(500, $response->status());
        $this->assertStringStartsWith('application/json', (string) $response->header('Content-Type'));
        $this->assertSame(['code' => 'FAIL', 'message' => '失败'], json_decode($response->body(), true));
    }

    public function test_alipay_notify_without_credentials_answers_200_plain_fail(): void
    {
        $response = $this->postRaw(
            '/api/payment/notify/alipay',
            http_build_query(['out_trade_no' => 'R1', 'trade_status' => 'TRADE_SUCCESS', 'sign' => 'bogus']),
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('text/plain', (string) $response->header('Content-Type'));
        $this->assertSame('fail', $response->body());
    }

    public function test_notify_routes_need_no_token(): void
    {
        $response = $this->postRaw('/api/payment/notify/alipay', 'a=b', ['Content-Type' => 'application/x-www-form-urlencoded']);

        $this->assertNotSame(401, $response->status());
        $this->assertStringNotContainsString('"code":401', $response->body(), '回调端点不能挂 ApiAuthMiddleware');
    }

    public function test_controller_passes_raw_body_lowercase_headers_and_form_to_the_gateway(): void
    {
        $wechat = new FakeGateway();
        $wechat->queue('verifyNotify', new NotifyResult(false, 'R_IGNORED'));
        $this->useFakeGateway($wechat);
        $body = '{"id":"EV-2","event_type":"TRANSACTION.CLOSED"}';

        $this->postRaw('/api/payment/notify/wechat', $body, [
            'Content-Type'        => 'application/json',
            'Wechatpay-Signature' => 'SIG==',
        ]);

        $calls = array_values(array_filter($wechat->calls(), static fn (array $c): bool => $c['method'] === 'verifyNotify'));
        $this->assertCount(1, $calls);
        /** @var \core\payment\dto\NotifyRequest $request */
        $request = $calls[0]['args'][0];
        $this->assertSame($body, $request->rawBody, '验签覆盖原始 body，控制器不得重新编码');
        $this->assertSame('SIG==', $request->headers['wechatpay-signature'] ?? null, '头名一律小写');
        $this->assertSame(array_change_key_case($request->headers, CASE_LOWER), $request->headers);
    }

    public function test_paid_notify_through_http_credits_once_even_when_repeated(): void
    {
        $user = $this->actingAsUser(['balance' => '0.00']);
        $this->userIds[] = $user->id;
        $now = date('Y-m-d H:i:s');
        $orderNo = 'RH' . bin2hex(random_bytes(8));
        $orderId = (int) Db::table('payment_orders')->insertGetId([
            'user_id'        => $user->id,
            'biz_type'       => 'recharge',
            'client_type'    => 'pc',
            'order_no'       => $orderNo,
            'app_id'         => 'wx5b6c4f2d8e9a1b3c',
            'channel'        => 'wechat',
            'trade_type'     => 'native',
            'subject'        => '余额充值',
            'amount_cents'   => 2550,
            'refunded_cents' => 0,
            'status'         => 'pending',
            'expires_at'     => date('Y-m-d H:i:s', time() + 1800),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $this->track('payment_orders', $orderId);

        $wechat = new FakeGateway();
        $paid = new NotifyResult(true, $orderNo, 'WX-HTTP-1', 2550, ['trade_state' => 'SUCCESS'], appId: 'wx5b6c4f2d8e9a1b3c');
        $wechat->queue('verifyNotify', $paid);
        $wechat->queue('verifyNotify', $paid);
        $this->useFakeGateway($wechat);
        $success = $wechat->notifyAck(true);

        foreach ([1, 2] as $attempt) {
            $response = $this->postRaw('/api/payment/notify/wechat', '{"id":"EV-3"}', ['Content-Type' => 'application/json']);
            $this->assertSame($success->status, $response->status(), "第 {$attempt} 次");
            $this->assertSame($success->body, $response->body(), "第 {$attempt} 次");
        }

        $this->assertSame('paid', (string) Db::table('payment_orders')->where('id', $orderId)->value('status'));
        $this->assertSame('25.50', (string) Db::table('users')->where('id', $user->id)->value('balance'));
        $this->assertSame(1, Db::table('balance_logs')->where('source', 'payment:' . $orderNo)->count(), '重复回调只入账一次');
    }
}
