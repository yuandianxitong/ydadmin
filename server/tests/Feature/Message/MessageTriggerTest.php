<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\model\payment\PaymentOrder;
use app\service\payment\MarkPaidOutcome;
use app\service\payment\PaymentService;
use app\service\user\BalanceService;
use app\service\wechat\WechatAuthService;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Message\FakeMessageChannels;
use tests\Support\Message\MessageFixtures;
use tests\Support\Wechat\FakeWechatHttp;
use tests\Support\Wechat\WechatUserFixtures;

/**
 * M6b spec §4.8：user_register（手机号注册、微信三条注册路径）、payment_success（markPaid 真实置已支付且为充值）。
 * 靠种子里两个内置模板（只开站内信），断言落库的站内信与消息日志。
 */
final class MessageTriggerTest extends ApiTestCase
{
    use FakeMessageChannels;
    use FakeWechatHttp;
    use MessageFixtures;
    use WechatUserFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMessageChannels();
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreMessageChannels();
            $this->restoreWechatHttp();
            if ($this->messageUserIds !== []) {
                Db::table('payment_orders')->whereIn('user_id', $this->messageUserIds)->delete();
                Db::table('balance_logs')->whereIn('user_id', $this->messageUserIds)->delete();
            }
        } finally {
            $this->cleanupMessageFixtures();
            $this->cleanupWechatFixtures();
            parent::tearDown();
        }
    }

    /** @return array<string, mixed> */
    private function onlyNotification(int $userId): array
    {
        $notifications = $this->notificationsFor($userId);
        $this->assertCount(1, $notifications);

        return $notifications[0];
    }

    // ---------------------------------------------------------------- user_register

    public function test_mobile_registration_sends_user_register_site_message(): void
    {
        $mobile = $this->fixtureMobile();
        Redis::set("sms_code:register:{$mobile}", '123456', 'EX', 300);

        $data = $this->post('/api/auth/register', [
            'mobile'                => $mobile,
            'password'              => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'code'                  => '123456',
        ])->assertOk()->data();
        $userId = (int) $data['user_info']['id'];
        $this->trackUser($userId);
        $this->messageUserIds[] = $userId;

        $notification = $this->onlyNotification($userId);
        $this->assertSame('注册成功', $notification['title']);
        $this->assertSame('欢迎加入，用户' . substr($mobile, -4), $notification['content']);
        $this->assertSame('system', $notification['type']);
        $this->assertEquals(['template_code' => 'user_register'], json_decode((string) $notification['extra'], true));

        $logs = $this->messageLogsFor($userId);
        $this->assertSame(['site'], array_column($logs, 'channel'), '内置模板的外发通道默认停用');
        $this->assertSame(1, (int) $logs[0]['status']);
        $this->assertSame([], $this->sentMessages());
    }

    public function test_rejected_registration_sends_nothing(): void
    {
        $existing = $this->messageUser();
        Redis::set("sms_code:register:{$existing->mobile}", '123456', 'EX', 300);

        $this->post('/api/auth/register', [
            'mobile'                => $existing->mobile,
            'password'              => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'code'                  => '123456',
        ])->assertCode(400);

        $this->assertSame([], $this->notificationsFor($existing->id));
        Redis::del("sms_code:register:{$existing->mobile}");
    }

    public function test_wechat_mini_registration_sends_user_register_site_message(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $this->fakeWechatHttp([self::wechatJson(['openid' => $openid, 'session_key' => 'SESSION-KEY'])]);
        $this->fakeMessageChannels(); // 决定 25：fakeWechatHttp 会重建微信通道，假通道须在其后重装

        $result = Container::get(WechatAuthService::class)->miniLogin('code-mini-1', '203.0.113.5');
        $userId = (int) $result['user_info']['id'];
        $this->messageUserIds[] = $userId;

        $notification = $this->onlyNotification($userId);
        $this->assertSame('欢迎加入，' . lang('wechat.default_nickname', [], 'zh_CN'), $notification['content']);
        $this->assertCount(1, $this->wechatRequests(), '站内信不调微信接口');
    }

    public function test_wechat_login_of_an_existing_member_sends_nothing(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $userId = $this->insertWechatUser(['mini_openid' => $openid]);
        $this->messageUserIds[] = $userId;
        $this->fakeWechatHttp([self::wechatJson(['openid' => $openid, 'session_key' => 'SESSION-KEY'])]);
        $this->fakeMessageChannels(); // 决定 25

        $result = Container::get(WechatAuthService::class)->miniLogin('code-mini-2', '203.0.113.5');

        $this->assertSame($userId, (int) $result['user_info']['id']);
        $this->assertSame([], $this->notificationsFor($userId));
    }

    // ---------------------------------------------------------------- payment_success

    /** @param array<string, mixed> $overrides */
    private function insertOrder(int $userId, array $overrides = []): string
    {
        $now = date('Y-m-d H:i:s');
        $orderNo = 'R' . date('YmdHis') . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        Db::table('payment_orders')->insert(array_merge([
            'user_id'      => $userId,
            'biz_type'     => PaymentOrder::BIZ_RECHARGE,
            'client_type'  => 'pc',
            'order_no'     => $orderNo,
            'channel'      => 'wechat',
            'trade_type'   => 'native',
            'subject'      => '余额充值',
            'amount_cents' => 5050,
            'status'       => PaymentOrder::STATUS_PENDING,
            'expires_at'   => date('Y-m-d H:i:s', time() + 1800),
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $overrides));

        return $orderNo;
    }

    private function payments(): PaymentService
    {
        return Container::get(PaymentService::class);
    }

    public function test_paid_recharge_sends_payment_success_site_message(): void
    {
        $user = $this->messageUser();
        $orderNo = $this->insertOrder($user->id);

        $this->assertSame(MarkPaidOutcome::PAID, $this->payments()->markPaid($orderNo, 'wechat', '4200000000000000001', 5050, []));

        $paidAt = (string) Db::table('payment_orders')->where('order_no', $orderNo)->value('paid_at');
        $notification = $this->onlyNotification($user->id);
        $this->assertSame('充值成功', $notification['title']);
        $this->assertSame("订单 {$orderNo} 已到账 50.50 元", $notification['content']);
        $this->assertSame('payment', $notification['type']);
        $this->assertSame($orderNo, $notification['biz_id']);

        $log = $this->messageLogsFor($user->id)[0];
        $this->assertSame('payment_success', $log['template_code']);
        $this->assertEquals(['order_no' => $orderNo, 'amount' => '50.50', 'paid_at' => $paidAt], json_decode((string) $log['variables'], true));
    }

    public function test_repeated_notify_and_mismatch_send_nothing_more(): void
    {
        $user = $this->messageUser();
        $paid = $this->insertOrder($user->id);
        $mismatch = $this->insertOrder($user->id);

        $this->payments()->markPaid($paid, 'wechat', 't1', 5050, []);
        $this->assertSame(MarkPaidOutcome::ALREADY, $this->payments()->markPaid($paid, 'wechat', 't1', 5050, []));
        $this->assertSame(MarkPaidOutcome::MISMATCH, $this->payments()->markPaid($mismatch, 'wechat', 't2', 1, []));

        $this->assertCount(1, $this->notificationsFor($user->id));
    }

    public function test_rolled_back_credit_sends_nothing(): void
    {
        $user = $this->messageUser();
        $orderNo = $this->insertOrder($user->id);
        $original = Container::get(BalanceService::class);
        Container::set(BalanceService::class, new class () extends BalanceService {
            public function change(int $userId, float $amount, int $type, string $source, string $remark = '', ?int $operatorId = null): array
            {
                throw new \RuntimeException('balance store down');
            }
        });
        Container::set(PaymentService::class, Container::make(PaymentService::class));
        try {
            try {
                $this->payments()->markPaid($orderNo, 'wechat', 't', 5050, []);
                $this->fail('入账失败必须上抛');
            } catch (\RuntimeException $e) {
                $this->assertSame('balance store down', $e->getMessage());
            }
        } finally {
            Container::set(BalanceService::class, $original);
            Container::set(PaymentService::class, Container::make(PaymentService::class));
        }

        $this->assertSame('pending', Db::table('payment_orders')->where('order_no', $orderNo)->value('status'));
        $this->assertSame([], $this->notificationsFor($user->id), '事务回滚，afterCommit 回调随之丢弃');
        $this->assertSame([], $this->messageLogsFor($user->id));
    }
}
