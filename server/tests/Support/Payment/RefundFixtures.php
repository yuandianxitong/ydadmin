<?php

declare(strict_types=1);

namespace tests\Support\Payment;

use app\service\payment\RefundService;
use core\payment\GatewayResolver;
use support\Container;
use support\Db;

/**
 * 退款与退款对账测试共用夹具（M5b Task 11/12）。只能用在 tests\Support\ApiTestCase 的子类里（依赖 track()）。
 *
 * 容器纪律：RefundService 是容器单例，#[Inject] 的 GatewayResolver 在它第一次被解析时就定死了。
 * 换成假网关后必须重新 make 一个 RefundService 放回容器——命令里的 Container::get() 才拿得到假网关；
 * 还原时同样要再 make 一次，否则后续用例拿到的仍是挂着假网关的实例。
 */
trait RefundFixtures
{
    private FakeGateway $wechatGateway;

    private FakeGateway $alipayGateway;

    private ?GatewayResolver $originalResolver = null;

    /** @var list<int> */
    private array $fixtureUserIds = [];

    /** @var list<int> */
    private array $fixtureOrderIds = [];

    /** 默认装两个假网关；传入 $resolver 时用它（例如模拟凭据不全）。 */
    private function installFakeGateways(?GatewayResolver $resolver = null): void
    {
        $this->originalResolver ??= Container::get(GatewayResolver::class);
        $this->wechatGateway = new FakeGateway();
        $this->alipayGateway = new FakeGateway();
        Container::set(GatewayResolver::class, $resolver ?? new FakeGatewayResolver([
            'wechat' => $this->wechatGateway,
            'alipay' => $this->alipayGateway,
        ]));
        Container::set(RefundService::class, Container::make(RefundService::class, []));
    }

    private function restoreGatewaysAndCleanup(): void
    {
        if ($this->originalResolver !== null) {
            Container::set(GatewayResolver::class, $this->originalResolver);
            $this->originalResolver = null;
        }
        Container::set(RefundService::class, Container::make(RefundService::class, []));

        if ($this->fixtureOrderIds !== []) {
            Db::table('refund_orders')->whereIn('payment_order_id', $this->fixtureOrderIds)->delete();
            Db::table('payment_orders')->whereIn('id', $this->fixtureOrderIds)->delete();
        }
        if ($this->fixtureUserIds !== []) {
            Db::table('balance_logs')->whereIn('user_id', $this->fixtureUserIds)->delete();
        }
        $this->fixtureOrderIds = [];
        $this->fixtureUserIds = [];
    }

    private function refundService(): RefundService
    {
        return Container::get(RefundService::class);
    }

    private function createMember(string $balance): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('users')->insertGetId([
            'nickname'   => 'rf_' . bin2hex(random_bytes(4)),
            'mobile'     => '17' . str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT),
            'password'   => password_hash('Passw0rd!', PASSWORD_DEFAULT),
            'status'     => 1,
            'balance'    => $balance,
            'points'     => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->track('users', $id);
        $this->fixtureUserIds[] = $id;

        return $id;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{id: int, order_no: string}
     */
    private function createPaidOrder(int $userId, int $amountCents, array $overrides = []): array
    {
        $now = date('Y-m-d H:i:s');
        $row = array_merge([
            'user_id'        => $userId,
            'biz_type'       => 'recharge',
            'client_type'    => 'pc',
            'order_no'       => 'RT' . bin2hex(random_bytes(8)),
            'trade_no'       => 'T' . bin2hex(random_bytes(6)),
            'channel'        => 'wechat',
            'trade_type'     => 'native',
            'subject'        => '余额充值',
            'amount_cents'   => $amountCents,
            'refunded_cents' => 0,
            'status'         => 'paid',
            'expires_at'     => $now,
            'paid_at'        => $now,
            'created_at'     => $now,
            'updated_at'     => $now,
        ], $overrides);
        $id = (int) Db::table('payment_orders')->insertGetId($row);
        $this->fixtureOrderIds[] = $id;

        return ['id' => $id, 'order_no' => (string) $row['order_no']];
    }

    /** @return array{id: int, refund_no: string} 直接插一条 processing 退款单（不扣余额，调用方自己把余额设成扣过之后的值） */
    private function createProcessingRefund(int $orderId, int $amountCents, string $createdAt): array
    {
        $refundNo = 'FT' . bin2hex(random_bytes(8));
        $id = (int) Db::table('refund_orders')->insertGetId([
            'refund_no'        => $refundNo,
            'payment_order_id' => $orderId,
            'amount_cents'     => $amountCents,
            'reason'           => '',
            'status'           => 'processing',
            'operator'         => 'cli:fixture',
            'created_at'       => $createdAt,
            'updated_at'       => $createdAt,
        ]);

        return ['id' => $id, 'refund_no' => $refundNo];
    }

    private function balanceOf(int $userId): string
    {
        return (string) Db::table('users')->where('id', $userId)->value('balance');
    }

    /** @return list<array{amount: string, type: int, source: string}> */
    private function balanceLogsOf(int $userId): array
    {
        return Db::table('balance_logs')->where('user_id', $userId)->orderBy('id')->get(['amount', 'type', 'source'])
            ->map(static fn (object $r): array => ['amount' => (string) $r->amount, 'type' => (int) $r->type, 'source' => (string) $r->source])
            ->all();
    }

    private function orderRow(int $orderId): object
    {
        $row = Db::table('payment_orders')->where('id', $orderId)->first();
        \PHPUnit\Framework\Assert::assertNotNull($row);

        return $row;
    }

    private function refundRow(string $refundNo): object
    {
        $row = Db::table('refund_orders')->where('refund_no', $refundNo)->first();
        \PHPUnit\Framework\Assert::assertNotNull($row);

        return $row;
    }

    private function refundCountOf(int $orderId): int
    {
        return Db::table('refund_orders')->where('payment_order_id', $orderId)->count();
    }
}
