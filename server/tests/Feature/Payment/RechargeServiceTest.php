<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use app\service\payment\RechargeService;
use core\exception\BusinessException;
use core\payment\dto\CreateOrderRequest;
use core\payment\dto\CreateOrderResult;
use core\payment\GatewayResolver;
use support\Container;
use support\Context;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;
use tests\Support\Payment\SwapsPaymentServices;

/** M5b spec §4 端 × 渠道矩阵、§5.1 限流与渠道开关。 */
final class RechargeServiceTest extends ApiTestCase
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
        $this->useResolver(['wechat' => true, 'alipay' => true]);
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

    /** @param array<string, bool> $enabled */
    private function useResolver(array $enabled): void
    {
        $this->swapPaymentDependency(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $this->wechat, 'alipay' => $this->alipay], $enabled));
    }

    private function service(): RechargeService
    {
        return Container::get(RechargeService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function user(array $attributes = []): int
    {
        $id = $this->actingAsUser($attributes)->id;
        $this->userIds[] = $id;

        return $id;
    }

    /** @return list<array{0: string, 1: string, 2: string}> [X-Client-Type, channel, trade_type] */
    public static function supportedCombinations(): array
    {
        return [
            ['pc', 'wechat', 'native'],
            ['pc', 'alipay', 'page'],
            ['h5', 'wechat', 'h5'],
            ['h5', 'alipay', 'wap'],
            ['app', 'wechat', 'app'],
            ['app', 'alipay', 'app'],
            ['wechat_h5', 'wechat', 'jsapi'],
            ['miniapp', 'wechat', 'jsapi'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('supportedCombinations')]
    public function test_matrix_resolves_trade_type(string $clientType, string $channel, string $tradeType): void
    {
        $userId = $this->user(['oa_openid' => 'o-official', 'mini_openid' => 'o-mini']);
        $gateway = $channel === 'wechat' ? $this->wechat : $this->alipay;
        $gateway->queue('create', new CreateOrderResult($tradeType, ['stub' => true]));

        $result = $this->service()->recharge($userId, '12.30', $channel, $clientType, '198.51.100.7');

        $this->assertSame($tradeType, $result['payment_data']['trade_type']);
        [$args] = $gateway->callsTo('create');
        /** @var CreateOrderRequest $request */
        $request = $args[0];
        $this->assertSame($tradeType, $request->tradeType);
        $this->assertSame(1230, $request->amountCents);
        $this->assertSame(lang('payment.recharge_subject'), $request->subject);
        $this->assertSame('198.51.100.7', $request->clientIp);
        $expectedOpenid = ['wechat_h5' => 'o-official', 'miniapp' => 'o-mini'][$clientType] ?? null;
        $this->assertSame($expectedOpenid, $request->openid, 'openid 只在 JSAPI 下传，且按端取对应列');

        $row = (array) Db::table('payment_orders')->where('order_no', $result['order_no'])->first();
        $this->assertSame($clientType, $row['client_type']);
        $this->assertSame($tradeType, $row['trade_type']);
        $this->assertSame('recharge', $row['biz_type']);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function rejectedCombinations(): array
    {
        return [
            ['wechat_h5', 'alipay'],
            ['miniapp', 'alipay'],
            ['', 'wechat'],
            ['', 'alipay'],
            ['desktop', 'wechat'],
            ['PC', 'wechat'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedCombinations')]
    public function test_unsupported_environment_is_rejected_before_touching_gateway(string $clientType, string $channel): void
    {
        $userId = $this->user();

        try {
            $this->service()->recharge($userId, '10', $channel, $clientType, '127.0.0.1');
            $this->fail('不支持的组合必须抛 BusinessException');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.client_not_supported'), $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }
        $this->assertSame([], $this->wechat->calls());
        $this->assertSame([], $this->alipay->calls());
        $this->assertSame(0, Db::table('payment_orders')->where('user_id', $userId)->count());
    }

    public function test_jsapi_without_openid_asks_for_wechat_authorization(): void
    {
        foreach (['wechat_h5', 'miniapp'] as $clientType) {
            $userId = $this->user();

            try {
                $this->service()->recharge($userId, '10', 'wechat', $clientType, '127.0.0.1');
                $this->fail("{$clientType} 没有 openid 必须报错");
            } catch (BusinessException $e) {
                $this->assertSame(lang('payment.wechat_auth_required'), $e->getMessage());
            }
            $this->assertSame(0, Db::table('payment_orders')->where('user_id', $userId)->count());
        }
    }

    public function test_openid_of_the_other_client_does_not_count(): void
    {
        $userId = $this->user(['mini_openid' => 'o-mini']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(lang('payment.wechat_auth_required'));
        $this->service()->recharge($userId, '10', 'wechat', 'wechat_h5', '127.0.0.1');
    }

    public function test_disabled_channel_is_unavailable_and_inserts_nothing(): void
    {
        $this->useResolver(['wechat' => false, 'alipay' => true]);
        $userId = $this->user();

        try {
            $this->service()->recharge($userId, '10', 'wechat', 'pc', '127.0.0.1');
            $this->fail('渠道关闭必须报不可用');
        } catch (BusinessException $e) {
            $this->assertSame(lang('payment.unavailable'), $e->getMessage());
        }
        $this->assertSame(0, Db::table('payment_orders')->where('user_id', $userId)->count());
        $this->assertSame([], $this->wechat->calls());
    }

    public function test_eleventh_attempt_within_a_minute_is_rate_limited_per_user(): void
    {
        $userId = $this->user();
        for ($i = 0; $i < 10; $i++) {
            try {
                $this->service()->recharge($userId, '10', 'alipay', 'miniapp', '127.0.0.1');
            } catch (BusinessException $e) {
                $this->assertSame(lang('payment.client_not_supported'), $e->getMessage(), "第 {$i} 次还没到限额，报的应是端类型错误");
            }
        }

        try {
            $this->service()->recharge($userId, '10', 'alipay', 'miniapp', '127.0.0.1');
            $this->fail('第 11 次必须被限流');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(lang('payment.rate_limited'), $e->getMessage());
        }
        $this->assertGreaterThan(0, (int) Redis::ttl("payment_rate:recharge:{$userId}"), '计数键必须带过期时间');

        $other = $this->user();
        $this->alipay->queue('create', new CreateOrderResult('page', ['body' => '<form></form>']));
        $this->assertSame('page', $this->service()->recharge($other, '10', 'alipay', 'pc', '127.0.0.1')['payment_data']['trade_type'], '限流按用户计数');
    }

    /** 直接插一张订单（不经服务，不计限流） */
    private function insertOrder(int $userId, string $status, string $expiresAt): void
    {
        $now = date('Y-m-d H:i:s');
        Db::table('payment_orders')->insert([
            'order_no'     => 'RP' . bin2hex(random_bytes(8)),
            'user_id'      => $userId,
            'biz_type'     => 'recharge',
            'client_type'  => 'pc',
            'channel'      => 'alipay',
            'trade_type'   => 'page',
            'subject'      => '余额充值',
            'amount_cents' => 1000,
            'status'       => $status,
            'expires_at'   => $expiresAt,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public function test_too_many_unexpired_pending_orders_are_refused_before_touching_gateway(): void
    {
        $this->assertSame(5, config('payment.max_pending_orders'));
        $userId = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->insertOrder($userId, 'pending', date('Y-m-d H:i:s', time() + 600));
        }

        try {
            $this->service()->recharge($userId, '10', 'alipay', 'pc', '127.0.0.1');
            $this->fail('已有 5 张未过期的待支付单时必须拒绝');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(lang('payment.too_many_pending'), $e->getMessage());
        }
        $this->assertSame(5, Db::table('payment_orders')->where('user_id', $userId)->count(), '不得插单');
        $this->assertSame([], $this->alipay->calls(), '不得调网关');
    }

    public function test_expired_paid_and_closed_orders_do_not_count_towards_the_pending_limit(): void
    {
        $userId = $this->user();
        for ($i = 0; $i < 4; $i++) {
            $this->insertOrder($userId, 'pending', date('Y-m-d H:i:s', time() + 600));
        }
        $this->insertOrder($userId, 'pending', date('Y-m-d H:i:s', time() - 60));
        $this->insertOrder($userId, 'pending', date('Y-m-d H:i:s', time() - 3600));
        $this->insertOrder($userId, 'paid', date('Y-m-d H:i:s', time() + 600));
        $this->insertOrder($userId, 'closed', date('Y-m-d H:i:s', time() + 600));
        $other = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->insertOrder($other, 'pending', date('Y-m-d H:i:s', time() + 600));
        }
        $this->alipay->queue('create', new CreateOrderResult('page', ['body' => '<form></form>']));

        $result = $this->service()->recharge($userId, '10', 'alipay', 'pc', '127.0.0.1');

        $this->assertSame('page', $result['payment_data']['trade_type'], '过期、已支付、已关闭与他人的订单都不计数');
    }

    public function test_order_subject_is_chinese_regardless_of_request_locale(): void
    {
        $userId = $this->user();
        $this->alipay->queue('create', new CreateOrderResult('page', ['body' => '<form></form>']));

        Context::set('locale', 'en');
        try {
            $result = $this->service()->recharge($userId, '10', 'alipay', 'pc', '127.0.0.1');
        } finally {
            Context::set('locale', null);
        }

        [$args] = $this->alipay->callsTo('create');
        $this->assertSame('余额充值', $args[0]->subject, '送给渠道的商品描述不随请求语言变化');
        $this->assertSame('余额充值', Db::table('payment_orders')->where('order_no', $result['order_no'])->value('subject'));
    }
}
