<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\service\payment\PaymentService;
use app\service\user\BalanceService;
use core\payment\config\AlipayConfig;
use core\payment\config\WechatPayConfig;
use core\payment\driver\AlipayDriver;
use core\payment\driver\WechatPayDriver;
use core\payment\dto\NotifyAck;
use core\payment\dto\NotifyRequest;
use core\payment\dto\NotifyResult;
use core\payment\exception\NotifyVerificationException;
use core\payment\exception\PaymentConfigException;
use core\payment\GatewayResolver;
use core\payment\PaymentGatewayInterface;
use core\payment\PaymentManager;
use Illuminate\Database\QueryException;
use Monolog\Handler\TestHandler;
use support\Container;
use support\Context;
use support\Db;
use support\Log;
use tests\Support\ApiTestCase;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;
use tests\Support\Payment\PaymentKeys;

/** 取网关就失败的解析器：模拟凭据不全 / 私钥无效（spec §5.3 回调仍须按渠道格式应答失败）。 */
final class BrokenGatewayResolver implements GatewayResolver
{
    public function isEnabled(string $channel): bool
    {
        return true;
    }

    public function gateway(string $channel): PaymentGatewayInterface
    {
        throw new PaymentConfigException("凭据不全：{$channel}");
    }
}

/**
 * spec §5.3：回调的判定与应答。网关换成 FakeGateway（不验签、不触网），只验 PaymentService 的分支；
 * 真实验签由 Task 3/5 的驱动测试与 Task 13 的 Test23 覆盖。
 */
final class PaymentNotifyServiceTest extends ApiTestCase
{
    private const WX_APP_ID = 'wx5b6c4f2d8e9a1b3c';

    private FakeGateway $wechat;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wechat = new FakeGateway();
        Container::set(GatewayResolver::class, new FakeGatewayResolver([
            'wechat' => $this->wechat,
            'alipay' => new FakeGateway(),
        ]));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
    }

    protected function tearDown(): void
    {
        Container::set(GatewayResolver::class, Container::get(PaymentManager::class));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
        if ($this->userIds !== []) {
            Db::table('balance_logs')->whereIn('user_id', $this->userIds)->delete();
        }
        foreach ($this->tempDirs as $dir) {
            PaymentKeys::removeDir($dir);
        }
        $this->userIds = [];
        $this->tempDirs = [];
        parent::tearDown();
    }

    private function service(): PaymentService
    {
        return Container::get(PaymentService::class);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{id:int, order_no:string, user_id:int}
     */
    private function createOrder(array $overrides = []): array
    {
        if (!array_key_exists('user_id', $overrides)) {
            $user = $this->actingAsUser(['balance' => '0.00']);
            $overrides['user_id'] = $user->id;
            $this->userIds[] = $user->id;
        }
        $now = date('Y-m-d H:i:s');
        $row = array_merge([
            'biz_type'       => 'recharge',
            'client_type'    => 'pc',
            'order_no'       => 'RT' . bin2hex(random_bytes(8)),
            'app_id'         => self::WX_APP_ID,
            'channel'        => 'wechat',
            'trade_type'     => 'native',
            'subject'        => '余额充值',
            'amount_cents'   => 1000,
            'refunded_cents' => 0,
            'status'         => 'pending',
            'expires_at'     => date('Y-m-d H:i:s', time() + 1800),
            'created_at'     => $now,
            'updated_at'     => $now,
        ], $overrides);
        $id = (int) Db::table('payment_orders')->insertGetId($row);
        $this->track('payment_orders', $id);

        return ['id' => $id, 'order_no' => (string) $row['order_no'], 'user_id' => (int) $row['user_id']];
    }

    private function request(): NotifyRequest
    {
        return new NotifyRequest(['wechatpay-serial' => 'X'], '{"id":"evt"}', []);
    }

    /** @return object{status:string, trade_no:?string} */
    private function orderRow(int $id): object
    {
        /** @var object{status:string, trade_no:?string} $row */
        $row = Db::table('payment_orders')->where('id', $id)->first();

        return $row;
    }

    public function test_paid_notify_marks_order_paid_credits_balance_and_acks_success(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T1', 1000, ['trade_state' => 'SUCCESS'], appId: self::WX_APP_ID));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(true), $ack);
        $this->assertSame('paid', $this->orderRow($order['id'])->status);
        $this->assertSame('WX-T1', $this->orderRow($order['id'])->trade_no);
        $this->assertSame('10.00', (string) Db::table('users')->where('id', $order['user_id'])->value('balance'));
        $this->assertSame(1, Db::table('balance_logs')->where('source', 'payment:' . $order['order_no'])->count());
    }

    public function test_duplicate_paid_notify_is_idempotent_and_still_acks_success(): void
    {
        $order = $this->createOrder();
        $result = new NotifyResult(true, $order['order_no'], 'WX-T2', 1000, [], appId: self::WX_APP_ID);
        $this->wechat->queue('verifyNotify', $result);
        $this->wechat->queue('verifyNotify', $result);

        $first = $this->service()->handleNotify('wechat', $this->request());
        $second = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(true), $first);
        $this->assertEquals($this->wechat->notifyAck(true), $second, 'ALREADY 也必须应答成功，否则渠道会一直重试');
        $this->assertSame('10.00', (string) Db::table('users')->where('id', $order['user_id'])->value('balance'));
        $this->assertSame(1, Db::table('balance_logs')->where('source', 'payment:' . $order['order_no'])->count());
    }

    public function test_verification_failure_acks_failure_and_touches_nothing(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyVerificationException('签名不符'));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
        $this->assertSame('pending', $this->orderRow($order['id'])->status);
        $this->assertSame(0, Db::table('balance_logs')->where('source', 'payment:' . $order['order_no'])->count());
    }

    public function test_unexpected_exception_during_verification_acks_failure(): void
    {
        $this->wechat->queue('verifyNotify', new \RuntimeException('解析炸了'));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
    }

    public function test_non_payment_event_acks_success_without_marking_paid(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyResult(false, $order['order_no']));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(true), $ack);
        $this->assertSame('pending', $this->orderRow($order['id'])->status);
    }

    public function test_amount_mismatch_acks_failure_and_leaves_order_pending(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T3', 999, [], appId: self::WX_APP_ID));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
        $this->assertSame('pending', $this->orderRow($order['id'])->status);
    }

    public function test_missing_paid_amount_is_treated_as_mismatch(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T4', null, [], appId: self::WX_APP_ID));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
        $this->assertSame('pending', $this->orderRow($order['id'])->status);
    }

    public function test_channel_mismatch_acks_failure(): void
    {
        $order = $this->createOrder(['channel' => 'alipay', 'trade_type' => 'page']);
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T5', 1000, [], appId: self::WX_APP_ID));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
        $this->assertSame('pending', $this->orderRow($order['id'])->status);
    }

    public function test_unknown_order_acks_failure(): void
    {
        $this->wechat->queue('verifyNotify', new NotifyResult(true, 'R_NOT_EXIST_' . bin2hex(random_bytes(4)), 'WX-T6', 1000, [], appId: self::WX_APP_ID));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
    }

    public function test_crediting_failure_rolls_back_and_acks_failure(): void
    {
        // 订单指向一个不存在的会员：BalanceService::change() 抛 NotFoundException，markPaid 整个事务回滚
        $order = $this->createOrder(['user_id' => 2_000_000_000]);
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T7', 1000, [], appId: self::WX_APP_ID));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack, '入账失败必须让渠道重试');
        $this->assertSame('pending', $this->orderRow($order['id'])->status, '入账失败时订单不能停在 paid');
    }

    public function test_database_failure_while_marking_paid_does_not_log_the_bound_payload(): void
    {
        $order = $this->createOrder();
        $openid = 'o-SECRET-openid-' . bin2hex(random_bytes(4));
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T8', 1000, ['payer' => ['openid' => $openid]], appId: self::WX_APP_ID));

        // 模拟写库失败：QueryException 的消息里带着 SQL 与绑定值（绑定值就是回调原文）
        $failing = new class ($openid) extends BalanceService {
            public function __construct(private readonly string $bound)
            {
            }

            public function change(int $userId, float $amount, int $type, string $source, string $remark = '', ?int $operatorId = null): array
            {
                $pdo = new class ('SQLSTATE[22001]: String data, right truncated') extends \PDOException {
                    /** @var string */
                    protected $code = '22001';
                };

                throw new QueryException('mysql', 'update `payment_orders` set `notify_data` = ?', [$this->bound], $pdo);
            }
        };

        $logs = new TestHandler();
        $original = Container::get(BalanceService::class);
        Container::set(BalanceService::class, $failing);
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
        Log::channel()->pushHandler($logs);
        try {
            $ack = $this->service()->handleNotify('wechat', $this->request());
        } finally {
            Log::channel()->popHandler();
            Container::set(BalanceService::class, $original);
        }

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
        $this->assertTrue($logs->hasErrorThatContains('置已支付或入账失败'));
        foreach ($logs->getRecords() as $record) {
            $this->assertStringNotContainsString($openid, json_encode([$record['message'], $record['context']], JSON_THROW_ON_ERROR), '日志不得带出 SQL 绑定的回调原文');
        }
        $record = $logs->getRecords()[array_key_last($logs->getRecords())];
        $this->assertSame(QueryException::class, $record['context']['exception']);
        $this->assertSame('22001', $record['context']['code'], '只记 SQLSTATE');
        $this->assertArrayNotHasKey('reason', $record['context']);
    }

    public function test_balance_log_remark_is_chinese_regardless_of_request_locale(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T9', 1000, [], appId: self::WX_APP_ID));

        Context::set('locale', 'en');
        try {
            $this->service()->handleNotify('wechat', $this->request());
        } finally {
            Context::set('locale', null);
        }

        $this->assertSame('在线充值', Db::table('balance_logs')->where('source', 'payment:' . $order['order_no'])->value('remark'));
    }

    public function test_gateway_unavailable_falls_back_to_channel_failure_ack(): void
    {
        Container::set(GatewayResolver::class, new BrokenGatewayResolver());
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));

        $wechat = $this->service()->handleNotify('wechat', $this->request());
        $alipay = $this->service()->handleNotify('alipay', new NotifyRequest([], 'a=b', ['a' => 'b']));

        $this->assertEquals(new NotifyAck(500, 'application/json', '{"code":"FAIL","message":"失败"}'), $wechat);
        $this->assertEquals(new NotifyAck(200, 'text/plain', 'fail'), $alipay);
    }

    public function test_notify_is_handled_even_when_channel_is_disabled(): void
    {
        $order = $this->createOrder();
        Container::set(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $this->wechat], ['wechat' => false, 'alipay' => false]));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-T8', 1000, [], appId: self::WX_APP_ID));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(true), $ack, 'spec §5.8：开关只拦新下单，关掉渠道后在途订单的回调照常入账');
        $this->assertSame('paid', $this->orderRow($order['id'])->status);
    }

    public function test_unknown_channel_failure_ack_is_plain_500(): void
    {
        $this->assertEquals(new NotifyAck(500, 'text/plain', 'fail'), $this->service()->notifyFailureAck('paypal'));
    }

    public function test_fallback_failure_ack_matches_real_drivers(): void
    {
        $alipayKeys = PaymentKeys::rsaPair();
        $platformKeys = PaymentKeys::rsaPair();
        $alipay = new AlipayDriver(new AlipayConfig('2021000000000000', $alipayKeys['private'], $platformKeys['public'], true, 5.0, 10.0));

        $dir = PaymentKeys::tempDir();
        $this->tempDirs[] = $dir;
        $merchant = PaymentKeys::rsaPair();
        $keyPath = $dir . '/apiclient_key.pem';
        file_put_contents($keyPath, $merchant['private']);
        $wechat = new WechatPayDriver(new WechatPayConfig(
            'wx0000000000000000',
            '1900000001',
            str_repeat('k', 32),
            'ABCDEF0123456789',
            $keyPath,
            'PUB_KEY_ID_0000000000000000000000000000',
            $platformKeys['public'],
            $dir . '/certs',
            60,
            5.0,
            10.0,
        ));

        $this->assertEquals($alipay->notifyAck(false), $this->service()->notifyFailureAck('alipay'));
        $this->assertEquals($wechat->notifyAck(false), $this->service()->notifyFailureAck('wechat'));
    }

    public function test_wechat_paid_notify_with_foreign_appid_is_refused_without_credit(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-FOREIGN', 1000, [], appId: 'wx0000000000000000'));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertEquals($this->wechat->notifyAck(false), $ack);
        $this->assertSame('pending', $this->orderRow($order['id'])->status);
        $this->assertSame(0, Db::table('balance_logs')->where('user_id', $order['user_id'])->count());
    }

    public function test_wechat_paid_notify_without_appid_is_refused(): void
    {
        $order = $this->createOrder();
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-NOAPP', 1000, []));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertSame(500, $ack->status);
        $this->assertSame('pending', $this->orderRow($order['id'])->status);
    }

    public function test_order_without_app_id_falls_back_to_pay_wechat_app_id(): void
    {
        $this->setConfig('pay_wechat_app_id', 'wxpay0000000000001');
        $order = $this->createOrder(['app_id' => null]);
        $this->wechat->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'WX-FALLBACK', 1000, [], appId: 'wxpay0000000000001'));

        $ack = $this->service()->handleNotify('wechat', $this->request());

        $this->assertSame(200, $ack->status);
        $this->assertSame('paid', $this->orderRow($order['id'])->status);
    }

    public function test_alipay_paid_notify_is_not_subject_to_appid_check(): void
    {
        $alipay = new FakeGateway();
        Container::set(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $this->wechat, 'alipay' => $alipay]));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
        $order = $this->createOrder(['channel' => 'alipay', 'trade_type' => 'page', 'app_id' => null]);
        $alipay->queue('verifyNotify', new NotifyResult(true, $order['order_no'], 'ALI-T1', 1000, []));

        $ack = $this->service()->handleNotify('alipay', $this->request());

        $this->assertSame('success', $ack->body);
        $this->assertSame('paid', $this->orderRow($order['id'])->status);
    }
}
