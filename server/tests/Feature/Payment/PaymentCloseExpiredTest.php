<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\service\payment\PaymentService;
use core\payment\dto\CreateOrderRequest;
use core\payment\dto\CreateOrderResult;
use core\payment\dto\NotifyAck;
use core\payment\dto\NotifyRequest;
use core\payment\dto\NotifyResult;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\dto\TradeQueryResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\exception\PaymentConfigException;
use core\payment\GatewayResolver;
use core\payment\PaymentGatewayInterface;
use core\payment\PaymentManager;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;

/** 查单永远「待支付」，关单时先执行一段回调（模拟关单请求在途时用户付款、回调抢先入账）。 */
final class CloseRaceGateway implements PaymentGatewayInterface
{
    /** @var list<string> */
    public array $closed = [];

    public function __construct(private readonly \Closure $onClose)
    {
    }

    public function create(CreateOrderRequest $request): CreateOrderResult
    {
        throw new \LogicException('不应被调用');
    }

    public function query(string $orderNo): TradeQueryResult
    {
        return new TradeQueryResult(TradeQueryResult::PENDING);
    }

    public function close(string $orderNo): void
    {
        ($this->onClose)($orderNo);
        $this->closed[] = $orderNo;
    }

    public function refund(RefundRequest $request): RefundResult
    {
        throw new \LogicException('不应被调用');
    }

    public function queryRefund(string $orderNo, string $refundNo): RefundResult
    {
        throw new \LogicException('不应被调用');
    }

    public function verifyNotify(NotifyRequest $request): NotifyResult
    {
        throw new \LogicException('不应被调用');
    }

    public function notifyAck(bool $success): NotifyAck
    {
        return new NotifyAck(200, 'text/plain', $success ? 'success' : 'fail');
    }
}

/** 取某个渠道时抛凭据异常，其余渠道交给内层解析器。 */
final class PartiallyBrokenResolver implements GatewayResolver
{
    public function __construct(private readonly GatewayResolver $inner, private readonly string $brokenChannel)
    {
    }

    public function isEnabled(string $channel): bool
    {
        return $this->inner->isEnabled($channel);
    }

    public function gateway(string $channel): PaymentGatewayInterface
    {
        if ($channel === $this->brokenChannel) {
            throw new PaymentConfigException("凭据不全：{$channel}");
        }

        return $this->inner->gateway($channel);
    }
}

/**
 * spec §5.5：关单任务。固定时钟 2026-09-16 12:00:00；过期单的 expires_at 放在 11:00，宽限 60 秒。
 * 断言以本用例建的订单为准；计数断言依赖「测试库里没有别人遗留的过期待支付单」——各支付测试都 track() 自己的订单。
 */
final class PaymentCloseExpiredTest extends ApiTestCase
{
    use ConfigOverride;

    private const NOW = '2026-09-16 12:00:00';

    private FakeGateway $wechat;

    private FakeGateway $alipay;

    /** @var list<int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wechat = new FakeGateway();
        $this->alipay = new FakeGateway();
        $this->useResolver(new FakeGatewayResolver(['wechat' => $this->wechat, 'alipay' => $this->alipay]));
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        Container::set(GatewayResolver::class, Container::get(PaymentManager::class));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
        if ($this->userIds !== []) {
            Db::table('balance_logs')->whereIn('user_id', $this->userIds)->delete();
        }
        $this->userIds = [];
        parent::tearDown();
    }

    private function useResolver(GatewayResolver $resolver): void
    {
        Container::set(GatewayResolver::class, $resolver);
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
    }

    private function service(): PaymentService
    {
        return Container::get(PaymentService::class);
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{id:int, order_no:string, user_id:int}
     */
    private function createOrder(array $overrides = []): array
    {
        $user = $this->actingAsUser(['balance' => '0.00']);
        $this->userIds[] = $user->id;
        $row = array_merge([
            'user_id'        => $user->id,
            'biz_type'       => 'recharge',
            'client_type'    => 'pc',
            'order_no'       => 'RC' . bin2hex(random_bytes(8)),
            'channel'        => 'wechat',
            'trade_type'     => 'native',
            'subject'        => '余额充值',
            'amount_cents'   => 1000,
            'refunded_cents' => 0,
            'status'         => 'pending',
            'expires_at'     => '2026-09-16 11:00:00',
            'created_at'     => '2026-09-16 10:30:00',
            'updated_at'     => '2026-09-16 10:30:00',
        ], $overrides);
        $id = (int) Db::table('payment_orders')->insertGetId($row);
        $this->track('payment_orders', $id);

        return ['id' => $id, 'order_no' => (string) $row['order_no'], 'user_id' => $user->id];
    }

    private function orderStatus(int $id): string
    {
        return (string) Db::table('payment_orders')->where('id', $id)->value('status');
    }

    /** @return list<string> 某个假网关上被调用的方法名（按顺序） */
    private function methods(FakeGateway $gateway): array
    {
        return array_map(static fn (array $call): string => (string) $call['method'], $gateway->calls());
    }

    public function test_gateway_paid_order_is_marked_paid_and_credited_without_closing(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PAID, 'WX-C1', 1000, ['trade_state' => 'SUCCESS']));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(['scanned' => 1, 'paid' => 1, 'closed' => 0, 'skipped' => 0], $counts);
        $this->assertSame('paid', $this->orderStatus($order['id']));
        $this->assertSame('10.00', (string) Db::table('users')->where('id', $order['user_id'])->value('balance'));
        $this->assertSame(['query'], $this->methods($this->wechat), '已支付的单绝不能再去关');
    }

    public function test_gateway_pending_order_is_closed_on_gateway_then_locally(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PENDING));
        $this->wechat->queue('close', null);

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(['scanned' => 1, 'paid' => 0, 'closed' => 1, 'skipped' => 0], $counts);
        $this->assertSame('closed', $this->orderStatus($order['id']));
        $this->assertSame(self::NOW, (string) Db::table('payment_orders')->where('id', $order['id'])->value('closed_at'));
        $this->assertSame(['query', 'close'], $this->methods($this->wechat));
    }

    public function test_order_unknown_to_gateway_is_closed(): void
    {
        $order = $this->createOrder(['channel' => 'alipay', 'trade_type' => 'page']);
        $this->alipay->queue('query', new TradeQueryResult(TradeQueryResult::NOT_FOUND));
        $this->alipay->queue('close', null);

        $this->service()->closeExpired(self::now());

        $this->assertSame('closed', $this->orderStatus($order['id']));
        $this->assertSame(['query', 'close'], $this->methods($this->alipay));
    }

    public function test_order_already_closed_on_gateway_is_closed_locally_without_calling_close(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::CLOSED));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(1, $counts['closed']);
        $this->assertSame('closed', $this->orderStatus($order['id']));
        $this->assertSame(['query'], $this->methods($this->wechat), '设计决定 6：查到 CLOSED 不再调 close()');
    }

    public function test_uncertain_query_result_skips_the_order(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('query', new GatewayResultUnknownException('读超时'));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(['scanned' => 1, 'paid' => 0, 'closed' => 0, 'skipped' => 1], $counts);
        $this->assertSame('pending', $this->orderStatus($order['id']));
        $this->assertSame(['query'], $this->methods($this->wechat));
    }

    public function test_failed_gateway_close_keeps_the_order_pending(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PENDING));
        $this->wechat->queue('close', new GatewayException('ORDERPAID'));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(1, $counts['skipped']);
        $this->assertSame('pending', $this->orderStatus($order['id']), '渠道没关成功就不能本地置 closed——用户可能正在付款');
    }

    public function test_orders_within_grace_period_or_not_yet_expired_are_not_scanned(): void
    {
        $inGrace = $this->createOrder(['expires_at' => '2026-09-16 11:59:30']);
        $future = $this->createOrder(['expires_at' => '2026-09-16 12:30:00']);

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(0, $counts['scanned']);
        $this->assertSame('pending', $this->orderStatus($inGrace['id']));
        $this->assertSame('pending', $this->orderStatus($future['id']));
        $this->assertSame([], $this->wechat->calls());
    }

    public function test_non_pending_orders_are_not_scanned(): void
    {
        $this->createOrder(['status' => 'paid', 'paid_at' => '2026-09-16 10:40:00']);
        $this->createOrder(['status' => 'closed', 'closed_at' => '2026-09-16 11:10:00']);

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(0, $counts['scanned']);
        $this->assertSame([], $this->wechat->calls());
    }

    public function test_one_failing_order_does_not_stop_the_round(): void
    {
        $broken = $this->createOrder(['expires_at' => '2026-09-16 10:50:00']);
        $healthy = $this->createOrder(['expires_at' => '2026-09-16 10:55:00']);
        // 按 expires_at 升序：先处理 broken（查单直接抛非网关异常），再处理 healthy
        $this->wechat->queue('query', new \RuntimeException('意外'));
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PENDING));
        $this->wechat->queue('close', null);

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(['scanned' => 2, 'paid' => 0, 'closed' => 1, 'skipped' => 1], $counts);
        $this->assertSame('pending', $this->orderStatus($broken['id']));
        $this->assertSame('closed', $this->orderStatus($healthy['id']));
    }

    public function test_unavailable_channel_credentials_skip_only_that_channels_orders(): void
    {
        $alipayOrder = $this->createOrder(['channel' => 'alipay', 'trade_type' => 'page', 'expires_at' => '2026-09-16 10:50:00']);
        $wechatOrder = $this->createOrder(['expires_at' => '2026-09-16 10:55:00']);
        $this->useResolver(new PartiallyBrokenResolver(
            new FakeGatewayResolver(['wechat' => $this->wechat, 'alipay' => $this->alipay]),
            'alipay'
        ));
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::CLOSED));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(['scanned' => 2, 'paid' => 0, 'closed' => 1, 'skipped' => 1], $counts);
        $this->assertSame('pending', $this->orderStatus($alipayOrder['id']));
        $this->assertSame('closed', $this->orderStatus($wechatOrder['id']));
    }

    public function test_batch_limit_takes_the_earliest_expired_first(): void
    {
        $this->overrideConfig('payment.close_batch', 1);
        $earliest = $this->createOrder(['expires_at' => '2026-09-16 10:00:00']);
        $later = $this->createOrder(['expires_at' => '2026-09-16 11:00:00']);
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::CLOSED));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(1, $counts['scanned']);
        $this->assertSame('closed', $this->orderStatus($earliest['id']));
        $this->assertSame('pending', $this->orderStatus($later['id']));
    }

    public function test_order_paid_by_callback_while_closing_stays_paid(): void
    {
        $order = $this->createOrder();
        $gateway = new CloseRaceGateway(function (string $orderNo): void {
            // 关单请求在途时回调抢先到达：markPaid 先提交
            Container::get(PaymentService::class)->markPaid($orderNo, 'wechat', 'WX-RACE', 1000, []);
        });
        $this->useResolver(new FakeGatewayResolver(['wechat' => $gateway, 'alipay' => $this->alipay]));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(['scanned' => 1, 'paid' => 0, 'closed' => 0, 'skipped' => 1], $counts);
        $this->assertSame('paid', $this->orderStatus($order['id']), '锁行后发现已非 pending，绝不能覆盖成 closed');
        $this->assertSame('10.00', (string) Db::table('users')->where('id', $order['user_id'])->value('balance'));
        $this->assertSame(1, Db::table('balance_logs')->where('source', 'payment:' . $order['order_no'])->count());
    }

    public function test_paid_result_that_markpaid_rejects_is_skipped(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PAID, 'WX-C9', 999, []));

        $counts = $this->service()->closeExpired(self::now());

        $this->assertSame(['scanned' => 1, 'paid' => 0, 'closed' => 0, 'skipped' => 1], $counts);
        $this->assertSame('pending', $this->orderStatus($order['id']), '金额不符不入账、也不关单，留给人工排查');
        $this->assertSame(['query'], $this->methods($this->wechat));
    }
}
