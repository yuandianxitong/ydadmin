<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use support\Db;
use tests\Support\ApiTestCase;

/** M5b spec §5.4 / §7.1：GET /api/payment/query。 */
final class PaymentQueryApiTest extends ApiTestCase
{
    private const URI = '/api/payment/query';

    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            Db::table('payment_orders')->whereIn('user_id', $this->userIds)->delete();
        }
        $this->userIds = [];
        parent::tearDown();
    }

    private function insertPaidOrder(int $userId): string
    {
        $now = date('Y-m-d H:i:s');
        $orderNo = 'R' . date('YmdHis') . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        Db::table('payment_orders')->insert([
            'user_id' => $userId, 'biz_type' => 'recharge', 'client_type' => 'pc', 'order_no' => $orderNo,
            'trade_no' => '4200000000000000001', 'channel' => 'wechat', 'trade_type' => 'native', 'subject' => '余额充值',
            'amount_cents' => 5000, 'status' => 'paid', 'expires_at' => date('Y-m-d H:i:s', time() + 1800),
            'paid_at' => '2026-09-16 12:00:00', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $orderNo;
    }

    public function test_requires_authentication(): void
    {
        $this->get(self::URI, ['order_no' => 'R1'])->assertCode(401);
    }

    public function test_own_order_returns_contract_fields_only(): void
    {
        $user = $this->actingAsUser();
        $this->userIds[] = $user->id;
        $orderNo = $this->insertPaidOrder($user->id);

        $data = $this->get(self::URI, ['order_no' => $orderNo], $user->token)->assertOk()->data();

        $this->assertSame(
            ['order_no' => $orderNo, 'status' => 'paid', 'amount' => '50.00', 'channel' => 'wechat', 'paid_at' => '2026-09-16 12:00:00'],
            $data,
            '不下发 trade_no、notify_data 等网关原始数据'
        );
    }

    public function test_other_users_order_is_404_like_a_missing_one(): void
    {
        $owner = $this->actingAsUser();
        $stranger = $this->actingAsUser();
        $this->userIds[] = $owner->id;
        $this->userIds[] = $stranger->id;
        $orderNo = $this->insertPaidOrder($owner->id);

        $foreign = $this->get(self::URI, ['order_no' => $orderNo], $stranger->token);
        $missing = $this->get(self::URI, ['order_no' => 'R0000000000000000000000'], $stranger->token);

        $foreign->assertCode(404);
        $missing->assertCode(404);
        $this->assertSame(lang('payment.order_not_found'), $foreign->message());
        $this->assertSame($missing->message(), $foreign->message());
        $this->assertSame($missing->data(), $foreign->data());
    }

    public function test_order_no_is_required_and_bounded(): void
    {
        $user = $this->actingAsUser();
        $this->userIds[] = $user->id;

        $this->get(self::URI, [], $user->token)->assertCode(422);
        $response = $this->get(self::URI, ['order_no' => str_repeat('9', 33)], $user->token);
        $response->assertCode(422);
        $this->assertArrayHasKey('order_no', $response->data()['errors']);
    }
}
