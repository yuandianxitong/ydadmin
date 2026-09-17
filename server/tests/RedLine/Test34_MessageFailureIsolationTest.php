<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\message\MessageTemplateRepository;
use core\payment\dto\NotifyResult;
use core\payment\GatewayResolver;
use core\queue\QueueDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;
use tests\Support\Payment\SwapsPaymentServices;

/** 故障注入的调用计数：证明故障真的被触发，而不是恰好没走到消息体系。 */
final class Test34FaultProbe
{
    public int $calls = 0;
}

/**
 * 红线（M6b spec §4.3 第 4 步、§4.8、§7.3）：消息体系故障不影响注册响应与支付回调应答。
 *
 * 两种故障 × 两个触发点：
 *   - 模板仓储抛异常（sendToUser 第一步就失败，靠外层 catch 吞掉）；
 *   - 队列投递抛异常（给种子模板临时打开短信通道，投递 message-send 时失败，靠通道级 catch 记 dispatch failed）。
 * 注册必须照常建号并登录；支付回调必须照常应答成功、置已支付、入账一次——应答失败会让渠道重复回调。
 */
final class Test34_MessageFailureIsolationTest extends ApiTestCase
{
    use SwapsPaymentServices;

    /** 依赖模板仓储 / 队列门面 / MessageService 的容器单例，按依赖顺序；PaymentService 由 rebuildPaymentServices() 重建 */
    private const MESSAGE_DEPENDENT_CLASSES = [
        'app\service\message\MessageDeliveryService',
        'app\queue\redis_slow\MessageSendConsumer',
        'app\service\message\MessageService',
        'app\service\user\UserAuthService',
        'app\service\wechat\WechatAuthService',
    ];

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<string> */
    private array $mobiles = [];

    /** @var array<string, array{sms_enabled: int, sms_template_id: string}> */
    private array $templateBackup = [];

    protected function tearDown(): void
    {
        try {
            Container::set(MessageTemplateRepository::class, Container::make(MessageTemplateRepository::class, []));
            Container::set(QueueDispatcher::class, Container::make(QueueDispatcher::class, []));
            $this->restorePaymentDependencies();
            $this->rebuildMessageDependents();
            foreach ($this->templateBackup as $code => $columns) {
                Db::table('message_templates')->where('code', $code)->update($columns);
            }
            foreach ($this->mobiles as $mobile) {
                Redis::del("sms_code:register:{$mobile}");
            }
            $registered = $this->mobiles === [] ? [] : Db::table('users')->whereIn('mobile', $this->mobiles)->pluck('id')->all();
            foreach ($registered as $id) {
                $this->trackUser((int) $id);
            }
            $ids = array_values(array_unique([...$this->userIds, ...array_map('intval', $registered)]));
            if ($ids !== []) {
                Db::table('message_logs')->whereIn('user_id', $ids)->delete();
                Db::table('user_notifications')->whereIn('user_id', $ids)->delete();
                Db::table('balance_logs')->whereIn('user_id', $ids)->delete();
            }
        } finally {
            $this->userIds = [];
            $this->mobiles = [];
            $this->templateBackup = [];
            parent::tearDown();
        }
    }

    /** @return array<string, array{string}> */
    public static function faults(): array
    {
        return [
            '模板仓储抛异常' => ['template_repository'],
            '队列投递抛异常' => ['queue_dispatch'],
        ];
    }

    #[DataProvider('faults')]
    public function test_register_response_is_unaffected_by_message_failures(string $fault): void
    {
        $probe = $this->injectFault($fault, 'user_register');
        $mobile = '137' . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        $this->mobiles[] = $mobile;
        Redis::set("sms_code:register:{$mobile}", '123456', 'EX', 300);

        $data = $this->post('/api/auth/register', [
            'mobile'                => $mobile,
            'password'              => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'code'                  => '123456',
        ])->assertOk()->data();

        $this->assertSame(['token', 'user_info'], array_keys($data));
        $this->assertSame($mobile, $data['user_info']['mobile']);
        $this->assertSame(1, Db::table('users')->where('mobile', $mobile)->count(), '注册照常建号');
        $this->assertGreaterThan(0, $probe->calls, '故障必须真的被触发，否则本用例什么也没验证');

        if ($fault === 'queue_dispatch') {
            $sms = Db::table('message_logs')->where('user_id', (int) $data['user_info']['id'])->where('channel', 'sms')->first();
            $this->assertNotNull($sms);
            $this->assertSame(2, (int) $sms->status);
            $this->assertStringStartsWith('dispatch failed', (string) $sms->error_msg);
        }
    }

    #[DataProvider('faults')]
    public function test_payment_notify_ack_is_unaffected_by_message_failures(string $fault): void
    {
        $user = $this->actingAsUser(['balance' => '0.00']);
        $this->userIds[] = $user->id;
        $probe = $this->injectFault($fault, 'payment_success');

        $now = date('Y-m-d H:i:s');
        $orderNo = 'R34' . bin2hex(random_bytes(8));
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
        $wechat->queue('verifyNotify', new NotifyResult(true, $orderNo, 'WX-T34', 2550, ['trade_state' => 'SUCCESS'], appId: 'wx5b6c4f2d8e9a1b3c'));
        $this->swapPaymentDependency(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $wechat, 'alipay' => new FakeGateway()]));
        $success = $wechat->notifyAck(true);

        $response = $this->postRaw('/api/payment/notify/wechat', '{"id":"EV-T34"}', ['Content-Type' => 'application/json']);

        $this->assertSame($success->status, $response->status());
        $this->assertSame($success->body, $response->body(), '消息体系故障不得把回调应答变成失败（渠道会重复回调）');
        $this->assertSame('paid', (string) Db::table('payment_orders')->where('id', $orderId)->value('status'));
        $this->assertSame('25.50', (string) Db::table('users')->where('id', $user->id)->value('balance'));
        $this->assertSame(1, Db::table('balance_logs')->where('source', 'payment:' . $orderNo)->count());
        $this->assertGreaterThan(0, $probe->calls, '故障必须真的被触发，否则本用例什么也没验证');
    }

    private function injectFault(string $fault, string $templateCode): Test34FaultProbe
    {
        $probe = new Test34FaultProbe();

        if ($fault === 'template_repository') {
            Container::set(MessageTemplateRepository::class, new class ($probe) extends MessageTemplateRepository {
                public function __construct(private readonly Test34FaultProbe $probe)
                {
                    parent::__construct();
                }

                public function findActiveByCode(string $code): ?array
                {
                    $this->probe->calls++;

                    throw new \RuntimeException('模拟模板仓储故障');
                }
            });
        } else {
            $row = Db::table('message_templates')->where('code', $templateCode)->first(['sms_enabled', 'sms_template_id']);
            $this->assertNotNull($row, "种子模板 {$templateCode} 不存在");
            $this->templateBackup[$templateCode] = ['sms_enabled' => (int) $row->sms_enabled, 'sms_template_id' => (string) $row->sms_template_id];
            Db::table('message_templates')->where('code', $templateCode)->update(['sms_enabled' => 1, 'sms_template_id' => 'SMS_T34']);

            Container::set(QueueDispatcher::class, new class ($probe) extends QueueDispatcher {
                public function __construct(private readonly Test34FaultProbe $probe)
                {
                }

                public function dispatch(string $queue, array $data): void
                {
                    if ($queue === 'message-send') {
                        $this->probe->calls++;

                        throw new \RuntimeException('模拟队列投递故障');
                    }
                    parent::dispatch($queue, $data);
                }
            });
        }

        $this->rebuildMessageDependents();

        return $probe;
    }

    private function rebuildMessageDependents(): void
    {
        foreach (self::MESSAGE_DEPENDENT_CLASSES as $class) {
            if (class_exists($class)) {
                Container::set($class, Container::make($class, []));
            }
        }
        $this->rebuildPaymentServices();
    }
}
