<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线（M5b spec §5.4、§12「query 泄漏他人订单与网关原文」）：C 端查询只能看到自己的订单。
 * 查别人的订单与查一个根本不存在的订单号，响应必须**逐字段相同**（code、message、data），否则攻击者
 * 可以枚举订单号探测「这个号存在、属于别人」。订单号是 R + 时间 + 8 位随机数，按秒枚举并不难。
 *
 * 夹具订单是 paid（已终结）：spec §5.4 只对 pending 补查网关，这里不触网。
 */
final class Test24_PaymentOrderCrossUserInvisibleTest extends ApiTestCase
{
    public function test_foreign_order_is_indistinguishable_from_missing_order(): void
    {
        $owner = $this->actingAsUser();
        $stranger = $this->actingAsUser();
        $orderNo = $this->paidOrder($owner->id);
        $missingNo = 'R' . date('YmdHis') . '99999999';
        $this->assertSame(0, Db::table('payment_orders')->where('order_no', $missingNo)->count(), '前置条件：对照订单号必须真的不存在');

        $foreign = $this->get('/api/payment/query', ['order_no' => $orderNo], $stranger->token);
        $missing = $this->get('/api/payment/query', ['order_no' => $missingNo], $stranger->token);

        $this->assertSame(200, $foreign->status());
        $foreign->assertCode(404);
        $missing->assertCode(404);
        $this->assertSame($missing->message(), $foreign->message(), '他人订单与不存在订单的 message 必须相同');
        $this->assertSame($missing->data(), $foreign->data(), '他人订单与不存在订单的 data 必须相同');
        $this->assertStringNotContainsString($orderNo, $foreign->body(), '响应体里不能回显他人的订单号');
        $this->assertStringNotContainsString('12.34', $foreign->body(), '响应体里不能出现他人订单金额');
    }

    public function test_owner_still_sees_own_order(): void
    {
        $owner = $this->actingAsUser();
        $orderNo = $this->paidOrder($owner->id);

        $response = $this->get('/api/payment/query', ['order_no' => $orderNo], $owner->token)->assertOk();

        // 正向对照：否则「一律 404」也能让上一条测试通过
        $data = (array) $response->data();
        $this->assertSame(['order_no', 'status', 'amount', 'channel', 'paid_at'], array_keys($data));
        $this->assertSame($orderNo, $data['order_no']);
        $this->assertSame('paid', $data['status']);
        $this->assertSame('12.34', $data['amount']);
        $this->assertSame('wechat', $data['channel']);
        $this->assertArrayNotHasKey('notify_data', $data, '不下发渠道原始报文');
    }

    private function paidOrder(int $userId): string
    {
        $orderNo = 'R' . date('YmdHis') . sprintf('%08d', random_int(0, 99_999_998));
        $now = date('Y-m-d H:i:s');
        $this->track('payment_orders', (int) Db::table('payment_orders')->insertGetId([
            'user_id'        => $userId,
            'biz_type'       => 'recharge',
            'client_type'    => 'pc',
            'order_no'       => $orderNo,
            'trade_no'       => '4200000000rl24' . random_int(1000, 9999),
            'channel'        => 'wechat',
            'trade_type'     => 'native',
            'subject'        => '余额充值',
            'amount_cents'   => 1234,
            'refunded_cents' => 0,
            'status'         => 'paid',
            'expires_at'     => date('Y-m-d H:i:s', time() + 1800),
            'paid_at'        => $now,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]));

        return $orderNo;
    }
}
