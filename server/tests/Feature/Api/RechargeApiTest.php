<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use core\payment\dto\CreateOrderRequest;
use core\payment\dto\CreateOrderResult;
use core\payment\GatewayResolver;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;
use tests\Support\Payment\PaymentKeys;
use tests\Support\Payment\SwapsPaymentServices;

/** M5b spec §7.1 / §7.2：POST /api/user/recharge。 */
final class RechargeApiTest extends ApiTestCase
{
    use SwapsPaymentServices;

    private const URI = '/api/user/recharge';

    private FakeGateway $wechat;

    /** @var list<int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wechat = new FakeGateway();
        $this->swapPaymentDependency(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $this->wechat]));
    }

    protected function tearDown(): void
    {
        try {
            $this->restorePaymentDependencies();
        } finally {
            foreach ($this->userIds as $id) {
                Redis::del("payment_rate:recharge:{$id}");
            }
            if ($this->userIds !== []) {
                Db::table('payment_orders')->whereIn('user_id', $this->userIds)->delete();
            }
            $this->userIds = [];
            parent::tearDown();
        }
    }

    private function token(): string
    {
        $user = $this->actingAsUser();
        $this->userIds[] = $user->id;

        return $user->token;
    }

    public function test_requires_authentication(): void
    {
        $this->post(self::URI, ['amount' => 10, 'channel' => 'wechat'], null, ['X-Client-Type' => 'pc'])->assertCode(401);
    }

    public function test_pc_wechat_returns_native_payload(): void
    {
        $this->wechat->queue('create', new CreateOrderResult('native', ['code_url' => 'weixin://wxpay/bizpayurl?pr=xyz']));

        $data = $this->post(self::URI, ['amount' => 50, 'channel' => 'wechat'], $this->token(), ['X-Client-Type' => 'pc'])->assertOk()->data();

        $this->assertSame(['order_no', 'payment_id', 'payment_data'], array_keys($data));
        $this->assertIsInt($data['payment_id']);
        $this->assertSame(['trade_type' => 'native', 'data' => ['code_url' => 'weixin://wxpay/bizpayurl?pr=xyz']], $data['payment_data']);
        $this->assertSame(5000, (int) Db::table('payment_orders')->where('order_no', $data['order_no'])->value('amount_cents'));
    }

    public function test_notify_url_ignores_the_request_host_header(): void
    {
        $this->wechat->queue('create', new CreateOrderResult('native', ['code_url' => 'weixin://x']));

        $this->post(self::URI, ['amount' => '10.5', 'channel' => 'wechat'], $this->token(), ['X-Client-Type' => 'pc', 'Host' => 'evil.example'])->assertOk();

        [$args] = $this->wechat->callsTo('create');
        /** @var CreateOrderRequest $request */
        $request = $args[0];
        $this->assertSame('http://localhost/api/payment/notify/wechat', $request->notifyUrl, '回调地址来自配置或 site_url，伪造 Host 无效');
        $this->assertSame(1050, $request->amountCents);
    }

    public function test_client_ip_ignores_untrusted_forwarded_for(): void
    {
        $this->wechat->queue('create', new CreateOrderResult('h5', ['h5_url' => 'https://wx.tenpay.com/x']));

        $this->post(self::URI, ['amount' => 10, 'channel' => 'wechat'], $this->token(), ['X-Client-Type' => 'h5', 'X-Forwarded-For' => '8.8.8.8'])->assertOk();

        [$args] = $this->wechat->callsTo('create');
        $this->assertSame('127.0.0.1', $args[0]->clientIp, '直连地址不是可信代理时不信 X-Forwarded-For');
    }

    public function test_missing_client_type_is_not_supported(): void
    {
        $response = $this->post(self::URI, ['amount' => 10, 'channel' => 'wechat'], $this->token());

        $response->assertCode(400);
        $this->assertSame(lang('payment.client_not_supported'), $response->message());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidBodies(): array
    {
        return [
            'three decimals'  => [['amount' => 1.234, 'channel' => 'wechat'], 'amount'],
            'below minimum'   => [['amount' => 0.99, 'channel' => 'wechat'], 'amount'],
            'above maximum'   => [['amount' => 10000.01, 'channel' => 'wechat'], 'amount'],
            'leading plus'    => [['amount' => '+10', 'channel' => 'wechat'], 'amount'],
            'bare fraction'   => [['amount' => '.5', 'channel' => 'wechat'], 'amount'],
            'not a number'    => [['amount' => 'ten', 'channel' => 'wechat'], 'amount'],
            'missing amount'  => [['channel' => 'wechat'], 'amount'],
            'unknown channel' => [['amount' => 10, 'channel' => 'paypal'], 'channel'],
            'missing channel' => [['amount' => 10], 'channel'],
        ];
    }

    /** @param array<string, mixed> $body */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBodies')]
    public function test_invalid_body_is_422_and_inserts_nothing(array $body, string $field): void
    {
        $response = $this->post(self::URI, $body, $this->token(), ['X-Client-Type' => 'pc']);

        $response->assertCode(422);
        $this->assertArrayHasKey($field, $response->data()['errors']);
        $this->assertSame([], $this->wechat->calls());
        $this->assertSame(0, Db::table('payment_orders')->whereIn('user_id', $this->userIds)->count());
    }

    public function test_boundary_amounts_are_accepted(): void
    {
        foreach ([1, '10000.00'] as $amount) {
            $this->wechat->queue('create', new CreateOrderResult('native', ['code_url' => 'weixin://x']));
            $this->post(self::URI, ['amount' => $amount, 'channel' => 'wechat'], $this->token(), ['X-Client-Type' => 'pc'])->assertOk();
        }
        $this->assertSame([100, 1_000_000], array_map(
            static fn (array $args): int => $args[0]->amountCents,
            $this->wechat->callsTo('create')
        ));
    }

    public function test_real_alipay_driver_builds_page_form_end_to_end(): void
    {
        // 不换假网关：验证容器绑定 + PaymentManager + AlipayDriver 真实串起来（page 下单是本地签名，不触网）
        $this->restorePaymentDependencies();
        $app = PaymentKeys::rsaPair();
        $alipay = PaymentKeys::rsaPair();
        $this->setConfig('pay_alipay_enabled', '1');
        $this->setConfig('pay_alipay_app_id', '2021000000000001');
        $this->setConfig('pay_alipay_private_key', $app['private']);
        $this->setConfig('pay_alipay_public_key', $alipay['public']);

        $data = $this->post(self::URI, ['amount' => 10, 'channel' => 'alipay'], $this->token(), ['X-Client-Type' => 'pc'])->assertOk()->data();

        $this->assertSame('page', $data['payment_data']['trade_type']);
        $this->assertStringContainsString('<form', (string) $data['payment_data']['data']['body']);
        $this->assertSame('page', Db::table('payment_orders')->where('order_no', $data['order_no'])->value('trade_type'));
    }

    public function test_disabled_channel_reports_unavailable(): void
    {
        $this->restorePaymentDependencies();

        $response = $this->post(self::URI, ['amount' => 10, 'channel' => 'alipay'], $this->token(), ['X-Client-Type' => 'pc']);

        $response->assertCode(400);
        $this->assertSame(lang('payment.unavailable'), $response->message(), '种子里两个渠道都是关闭的');
    }
}
