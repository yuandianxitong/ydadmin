<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\model\payment\PaymentOrder;
use app\model\user\BalanceLog;
use app\service\payment\MarkPaidOutcome;
use app\service\payment\OrderNoGenerator;
use app\service\payment\PaymentService;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\payment\dto\CreateOrderRequest;
use core\payment\dto\CreateOrderResult;
use core\payment\dto\TradeQueryResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\GatewayResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;
use tests\Support\Payment\SwapsPaymentServices;

/** M5b spec §5.1 / §5.2 / §5.4：下单、唯一的置已支付入口、订单查询与节流补查。 */
final class PaymentServiceTest extends ApiTestCase
{
    use SwapsPaymentServices;

    private FakeGateway $wechat;

    private FakeGateway $alipay;

    /** @var list<int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wechat = new FakeGateway();
        $this->alipay = new FakeGateway();
        $this->swapPaymentDependency(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $this->wechat, 'alipay' => $this->alipay]));
    }

    protected function tearDown(): void
    {
        $this->restorePaymentDependencies();
        if ($this->userIds !== []) {
            foreach (Db::table('payment_orders')->whereIn('user_id', $this->userIds)->pluck('order_no') as $orderNo) {
                Redis::del("payment:query_throttle:{$orderNo}");
            }
            Db::table('payment_orders')->whereIn('user_id', $this->userIds)->delete();
            Db::table('balance_logs')->whereIn('user_id', $this->userIds)->delete();
        }
        $this->userIds = [];
        parent::tearDown();
    }

    private function service(): PaymentService
    {
        return Container::get(PaymentService::class);
    }

    private function user(array $attributes = []): int
    {
        $id = $this->actingAsUser($attributes)->id;
        $this->userIds[] = $id;

        return $id;
    }

    /** @param array<string, mixed> $overrides */
    private function insertOrder(int $userId, array $overrides = []): string
    {
        $now = date('Y-m-d H:i:s');
        $orderNo = (string) ($overrides['order_no'] ?? 'R' . date('YmdHis') . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT));
        Db::table('payment_orders')->insert(array_merge([
            'user_id'      => $userId,
            'biz_type'     => PaymentOrder::BIZ_RECHARGE,
            'client_type'  => 'pc',
            'order_no'     => $orderNo,
            'channel'      => 'wechat',
            'trade_type'   => 'native',
            'subject'      => '余额充值',
            'amount_cents' => 5000,
            'status'       => PaymentOrder::STATUS_PENDING,
            'expires_at'   => date('Y-m-d H:i:s', time() + 1800),
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $overrides));

        return $orderNo;
    }

    /** @return array<string, mixed> */
    private function order(string $orderNo): array
    {
        return (array) Db::table('payment_orders')->where('order_no', $orderNo)->first();
    }

    // ---------------------------------------------------------------- createOrder

    public function test_create_order_inserts_pending_row_and_returns_gateway_payload(): void
    {
        $userId = $this->user();
        $this->wechat->queue('create', new CreateOrderResult('native', ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc']));

        $result = $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'wechat', 'native', '余额充值', 5000, null, '203.0.113.9');

        $this->assertSame(['order_no', 'payment_id', 'payment_data'], array_keys($result));
        $this->assertMatchesRegularExpression('/^R\d{14}\d{8}$/', $result['order_no'], '充值单号：R + YmdHis + 8 位随机数');
        $this->assertSame(['trade_type' => 'native', 'data' => ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc']], $result['payment_data']);

        $row = $this->order($result['order_no']);
        $this->assertSame($result['payment_id'], (int) $row['id']);
        $this->assertSame($userId, (int) $row['user_id']);
        $this->assertSame('recharge', $row['biz_type']);
        $this->assertSame('pc', $row['client_type']);
        $this->assertSame('wechat', $row['channel']);
        $this->assertSame('native', $row['trade_type']);
        $this->assertSame(5000, (int) $row['amount_cents']);
        $this->assertSame(0, (int) $row['refunded_cents']);
        $this->assertSame('pending', $row['status']);
        $this->assertEqualsWithDelta(time() + 30 * 60, strtotime((string) $row['expires_at']), 5, '过期时间 = 创建 + 30 分钟');

        [$args] = $this->wechat->callsTo('create');
        /** @var CreateOrderRequest $request */
        $request = $args[0];
        $this->assertSame($result['order_no'], $request->orderNo);
        $this->assertSame('native', $request->tradeType);
        $this->assertSame('余额充值', $request->subject);
        $this->assertSame(5000, $request->amountCents);
        $this->assertSame((string) $row['expires_at'], $request->expiresAt->format('Y-m-d H:i:s'), '传给网关的过期时间与库里是同一秒');
        $this->assertSame('http://localhost/api/payment/notify/wechat', $request->notifyUrl, '回调地址默认按 site_url 拼');
        $this->assertNull($request->openid);
        $this->assertSame('203.0.113.9', $request->clientIp);
    }

    public function test_notify_url_prefers_channel_config_then_site_url_without_trailing_slash(): void
    {
        $userId = $this->user();

        $this->setConfig('pay_alipay_notify_url', ' https://pay.example.com/alipay-callback ');
        $this->alipay->queue('create', new CreateOrderResult('page', ['body' => '<form></form>']));
        $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'alipay', 'page', '余额充值', 100, null, null);

        $this->setConfig('pay_alipay_notify_url', '');
        $this->setConfig('site_url', 'https://shop.example.com/');
        $this->alipay->queue('create', new CreateOrderResult('page', ['body' => '<form></form>']));
        $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'alipay', 'page', '余额充值', 100, null, null);

        $this->setConfig('pay_alipay_notify_url', '/api/payment/notify/alipay');
        $this->alipay->queue('create', new CreateOrderResult('page', ['body' => '<form></form>']));
        $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'alipay', 'page', '余额充值', 100, null, null);

        $calls = $this->alipay->callsTo('create');
        $this->assertSame('https://pay.example.com/alipay-callback', $calls[0][0]->notifyUrl);
        $this->assertSame('https://shop.example.com/api/payment/notify/alipay', $calls[1][0]->notifyUrl);
        $this->assertSame('https://shop.example.com/api/payment/notify/alipay', $calls[2][0]->notifyUrl, '相对路径同样拼网站地址');
    }

    public function test_create_order_stores_and_passes_app_id(): void
    {
        $userId = $this->user();
        $this->wechat->queue('create', new CreateOrderResult('jsapi', ['appId' => 'wxmini000000000001']));

        $result = $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'miniapp', 'wechat', 'jsapi', '余额充值', 100, 'o-mini', null, 'wxmini000000000001');

        $this->assertSame('wxmini000000000001', Db::table('payment_orders')->where('order_no', $result['order_no'])->value('app_id'));
        [$args] = $this->wechat->callsTo('create');
        $this->assertSame('wxmini000000000001', $args[0]->appId);
    }

    public function test_create_order_without_app_id_stores_null(): void
    {
        $userId = $this->user();
        $this->wechat->queue('create', new CreateOrderResult('native', ['code_url' => 'weixin://x']));

        $result = $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'wechat', 'native', '余额充值', 100, null, null);

        $this->assertNull(Db::table('payment_orders')->where('order_no', $result['order_no'])->value('app_id'));
    }

    public function test_incomplete_credentials_insert_nothing_and_say_unavailable(): void
    {
        $userId = $this->user();
        $this->swapPaymentDependency(GatewayResolver::class, new FakeGatewayResolver(['alipay' => $this->alipay]));

        try {
            $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'wechat', 'native', '余额充值', 100, null, null);
            $this->fail('凭据不全必须抛 BusinessException');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.unavailable'), $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }
        $this->assertSame(0, Db::table('payment_orders')->where('user_id', $userId)->count(), '先取网关后插单，不留孤儿订单');
    }

    public function test_definite_gateway_failure_closes_the_order_with_reason(): void
    {
        $userId = $this->user();
        $this->wechat->queue('create', new GatewayException('INVALID_REQUEST: 商户号与 appid 不匹配'));

        try {
            $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'wechat', 'native', '余额充值', 100, null, null);
            $this->fail('网关明确失败必须抛 BusinessException');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.create_failed'), $e->getMessage());
        }

        $row = (array) Db::table('payment_orders')->where('user_id', $userId)->first();
        $this->assertSame('closed', $row['status']);
        $this->assertNotNull($row['closed_at']);
        $this->assertSame('INVALID_REQUEST: 商户号与 appid 不匹配', $row['error_msg']);
    }

    public function test_unknown_gateway_result_keeps_the_order_pending(): void
    {
        $userId = $this->user();
        $this->wechat->queue('create', new GatewayResultUnknownException('读超时'));

        try {
            $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'wechat', 'native', '余额充值', 100, null, null);
            $this->fail('结果不确定也要告诉用户下单失败');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.create_failed'), $e->getMessage());
        }

        $row = (array) Db::table('payment_orders')->where('user_id', $userId)->first();
        $this->assertSame('pending', $row['status'], '渠道那边可能已经建单，交给补查与关单任务收尾');
        $this->assertNull($row['closed_at']);
    }

    public function test_order_number_collision_is_retried_up_to_three_times(): void
    {
        $userId = $this->user();
        $taken = $this->insertOrder($userId, ['order_no' => 'R2026091600000000000001']);
        $generator = new class ([$taken, $taken, $taken, 'R2026091600000000000002']) extends OrderNoGenerator {
            /** @param list<string> $numbers */
            public function __construct(public array $numbers)
            {
            }

            public function generate(string $prefix): string
            {
                return (string) array_shift($this->numbers);
            }
        };
        $this->swapPaymentDependency(OrderNoGenerator::class, $generator);
        $this->wechat->queue('create', new CreateOrderResult('native', ['code_url' => 'weixin://x']));

        $result = $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'wechat', 'native', '余额充值', 100, null, null);

        $this->assertSame('R2026091600000000000002', $result['order_no']);
        $this->assertSame([], $generator->numbers, '初次 + 3 次重生成');
    }

    public function test_fourth_collision_is_rethrown(): void
    {
        $userId = $this->user();
        $taken = $this->insertOrder($userId, ['order_no' => 'R2026091600000000000003']);
        $generator = new class ($taken) extends OrderNoGenerator {
            public int $calls = 0;

            public function __construct(private readonly string $taken)
            {
            }

            public function generate(string $prefix): string
            {
                $this->calls++;

                return $this->taken;
            }
        };
        $this->swapPaymentDependency(OrderNoGenerator::class, $generator);

        try {
            $this->service()->createOrder($userId, PaymentOrder::BIZ_RECHARGE, 'pc', 'wechat', 'native', '余额充值', 100, null, null);
            $this->fail('连撞 4 次必须原样抛出');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(4, $generator->calls);
        }
        $this->assertSame([], $this->wechat->callsTo('create'), '没插进单就不能调网关');
    }

    // ---------------------------------------------------------------- markPaid

    public function test_mark_paid_credits_balance_once_in_the_same_transaction(): void
    {
        $userId = $this->user(['balance' => '10.00']);
        $orderNo = $this->insertOrder($userId, ['amount_cents' => 5050]);

        $outcome = $this->service()->markPaid($orderNo, 'wechat', '4200000000000000001', 5050, ['trade_state' => 'SUCCESS', 'payer' => ['openid' => 'o-1']]);

        $this->assertSame(MarkPaidOutcome::PAID, $outcome);
        $row = $this->order($orderNo);
        $this->assertSame('paid', $row['status']);
        $this->assertSame('4200000000000000001', $row['trade_no']);
        $this->assertNotNull($row['paid_at']);
        // JSON 列取回时对象键序会变：assertEquals 比内容不比键序
        $this->assertEquals(['trade_state' => 'SUCCESS', 'payer' => ['openid' => 'o-1']], json_decode((string) $row['notify_data'], true));

        $logs = Db::table('balance_logs')->where('user_id', $userId)->get()->all();
        $this->assertCount(1, $logs);
        $this->assertSame('50.50', (string) $logs[0]->amount);
        $this->assertSame(BalanceLog::TYPE_RECHARGE, (int) $logs[0]->type);
        $this->assertSame('payment:' . $orderNo, $logs[0]->source);
        $this->assertSame(lang('payment.recharge_remark'), $logs[0]->remark);
        $this->assertNull($logs[0]->operator_id);
        $this->assertSame('60.50', (string) Db::table('users')->where('id', $userId)->value('balance'));
    }

    public function test_mark_paid_is_idempotent_for_paid_and_refunded_orders(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId);
        $this->assertSame(MarkPaidOutcome::PAID, $this->service()->markPaid($orderNo, 'wechat', 't-1', 5000, []));
        $this->assertSame(MarkPaidOutcome::ALREADY, $this->service()->markPaid($orderNo, 'wechat', 't-1', 5000, []));

        $refunded = $this->insertOrder($userId, ['status' => 'refunded', 'refunded_cents' => 5000]);
        $this->assertSame(MarkPaidOutcome::ALREADY, $this->service()->markPaid($refunded, 'wechat', 't-2', 5000, []));

        $this->assertSame(1, Db::table('balance_logs')->where('user_id', $userId)->count(), '重复回调只入账一次');
        $this->assertSame('refunded', $this->order($refunded)['status'], '已退款的单不能被回调改回 paid');
    }

    public function test_amount_or_channel_mismatch_changes_nothing(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId, ['amount_cents' => 5000, 'channel' => 'wechat']);

        $this->assertSame(MarkPaidOutcome::MISMATCH, $this->service()->markPaid($orderNo, 'wechat', 't', 4999, []));
        $this->assertSame(MarkPaidOutcome::MISMATCH, $this->service()->markPaid($orderNo, 'alipay', 't', 5000, []));

        $row = $this->order($orderNo);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['trade_no']);
        $this->assertNull($row['notify_data']);
        $this->assertSame(0, Db::table('balance_logs')->where('user_id', $userId)->count());
    }

    public function test_unknown_order_is_not_found(): void
    {
        $this->assertSame(MarkPaidOutcome::NOT_FOUND, $this->service()->markPaid('R0000000000000000000000', 'wechat', 't', 1, []));
    }

    public function test_closed_order_that_was_actually_paid_is_credited(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId, ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')]);

        $this->assertSame(MarkPaidOutcome::PAID, $this->service()->markPaid($orderNo, 'wechat', 't', 5000, []));

        $this->assertSame('paid', $this->order($orderNo)['status'], '关单与付款擦肩而过：钱确实收到了，照样入账');
        $this->assertSame(1, Db::table('balance_logs')->where('user_id', $userId)->count());
    }

    public function test_credit_failure_rolls_back_the_status_change(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId);
        // 会员被软删：BalanceService::change() 在行锁读里找不到人，抛 NotFoundException
        Db::table('users')->where('id', $userId)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        try {
            $this->service()->markPaid($orderNo, 'wechat', 't', 5000, []);
            $this->fail('入账失败必须上抛，让回调应答失败、渠道重试');
        } catch (NotFoundException) {
        }

        $row = $this->order($orderNo);
        $this->assertSame('pending', $row['status'], '入账与置已支付同事务：入账失败，订单状态回滚');
        $this->assertNull($row['paid_at']);
    }

    // ---------------------------------------------------------------- queryForUser

    public function test_query_returns_contract_shape_for_own_order(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId, ['status' => 'paid', 'amount_cents' => 1230, 'channel' => 'alipay', 'paid_at' => '2026-09-16 10:00:00']);

        $data = $this->service()->queryForUser($orderNo, $userId);

        $this->assertSame(
            ['order_no' => $orderNo, 'status' => 'paid', 'amount' => '12.30', 'channel' => 'alipay', 'paid_at' => '2026-09-16 10:00:00'],
            $data
        );
        $this->assertSame([], $this->alipay->calls(), '已终结的单不打网关');
    }

    public function test_other_users_order_and_missing_order_look_the_same(): void
    {
        $owner = $this->user();
        $stranger = $this->user();
        $orderNo = $this->insertOrder($owner);

        foreach ([[$orderNo, $stranger], ['R0000000000000000000000', $owner]] as [$no, $viewer]) {
            try {
                $this->service()->queryForUser($no, $viewer);
                $this->fail('非本人或不存在都必须 404');
            } catch (NotFoundException $e) {
                $this->assertSame(lang('payment.order_not_found'), $e->getMessage());
                $this->assertSame(404, $e->getCode());
            }
        }
        $this->assertSame([], $this->wechat->calls(), '越权查询不能触发补查');
    }

    public function test_pending_order_is_rechecked_at_most_once_per_throttle_window(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId);
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PENDING));

        $this->assertSame('pending', $this->service()->queryForUser($orderNo, $userId)['status']);
        $this->assertSame('pending', $this->service()->queryForUser($orderNo, $userId)['status']);

        $this->assertCount(1, $this->wechat->callsTo('query'), '10 秒内第二次查询只读本地');
        $this->assertGreaterThan(0, (int) Redis::ttl("payment:query_throttle:{$orderNo}"));
    }

    public function test_recheck_finding_payment_credits_the_order(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId, ['amount_cents' => 5000]);
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PAID, '4200000000000000009', 5000, ['trade_state' => 'SUCCESS']));

        $data = $this->service()->queryForUser($orderNo, $userId);

        $this->assertSame('paid', $data['status']);
        $this->assertNotNull($data['paid_at']);
        $this->assertSame('4200000000000000009', $this->order($orderNo)['trade_no']);
        $this->assertSame(1, Db::table('balance_logs')->where('user_id', $userId)->where('source', 'payment:' . $orderNo)->count(), '补查到已支付也经 markPaid 入账');
    }

    public function test_gateway_errors_during_recheck_fall_back_to_local_status(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId);
        $this->wechat->queue('query', new GatewayResultUnknownException('连接超时'));

        $this->assertSame('pending', $this->service()->queryForUser($orderNo, $userId)['status']);
    }

    public function test_recheck_with_mismatched_amount_does_not_credit(): void
    {
        $userId = $this->user();
        $orderNo = $this->insertOrder($userId, ['amount_cents' => 5000]);
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::PAID, 't', 1, []));

        $this->assertSame('pending', $this->service()->queryForUser($orderNo, $userId)['status']);
        $this->assertSame(0, Db::table('balance_logs')->where('user_id', $userId)->count());
    }
}
