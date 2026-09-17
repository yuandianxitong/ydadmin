<?php

declare(strict_types=1);

namespace app\service\payment;

use app\repository\payment\PaymentOrderRepository;
use app\repository\user\BalanceLogRepository;
use app\service\user\BalanceService;
use core\base\Service;
use core\contract\ConfigValueReader;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\payment\Channel;
use core\payment\dto\CreateOrderRequest;
use core\payment\dto\NotifyAck;
use core\payment\dto\NotifyRequest;
use core\payment\dto\TradeQueryResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\exception\NotifyVerificationException;
use core\payment\exception\PaymentConfigException;
use core\payment\exception\PaymentException;
use core\payment\ExceptionLogContext;
use core\payment\GatewayResolver;
use core\payment\Money;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Log;
use support\Redis;

/**
 * 支付订单（M5b spec §5）。
 *
 * markPaid() 是**唯一**把订单改成已支付的入口：回调（Task 9）、查询补查（本类）、关单任务（Task 10）都经它。
 * 置已支付与余额入账在同一个事务里完成（BalanceService::change() 嵌套走 savepoint）——入账不是外部副作用，
 * 不需要拖到提交之后；这样从结构上消除了 1.x「查询先置已支付不入账、回调再跳过」导致永不入账的竞态，
 * 也不会出现「订单已支付、入账监听器失败被吞」。行锁顺序固定为「订单行 → 用户行」。
 *
 * 容器单例，无实例态。
 */
class PaymentService extends Service
{
    /** 业务类型 → 单号前缀 */
    private const ORDER_NO_PREFIXES = [PaymentOrderRepository::BIZ_RECHARGE => 'R'];

    /** 撞唯一键后的最多重生成次数（初次 + 3 次） */
    private const MAX_ORDER_NO_RETRIES = 3;

    #[Inject]
    protected PaymentOrderRepository $orders;

    #[Inject]
    protected BalanceService $balanceService;

    #[Inject]
    protected GatewayResolver $gateways;

    #[Inject]
    protected ConfigValueReader $config;

    #[Inject]
    protected OrderNoGenerator $orderNoGenerator;

    /**
     * 插入 pending 订单 → 事务外调网关下单（spec §5.1 步骤 4–7）。渠道开关由调用方判断（RechargeService）。
     *
     * @param ?string $appId 下单所用 appid（M6a spec §7，微信按端解析；支付宝为 null）
     * @return array{order_no: string, payment_id: int, payment_data: array{trade_type: string, data: array<string, mixed>}}
     * @throws BusinessException 凭据不全（payment.unavailable，不插单）；网关失败或结果不确定（payment.create_failed）
     */
    public function createOrder(
        int $userId,
        string $bizType,
        string $clientType,
        string $channel,
        string $tradeType,
        string $subject,
        int $amountCents,
        ?string $openid,
        ?string $clientIp,
        ?string $appId = null,
    ): array {
        $prefix = self::ORDER_NO_PREFIXES[$bizType] ?? throw new \InvalidArgumentException("未知支付业务类型：{$bizType}");

        // 先取网关再插单：凭据不全时不留孤儿 pending 订单
        try {
            $gateway = $this->gateways->gateway($channel);
        } catch (PaymentConfigException $e) {
            Log::warning('支付渠道配置不可用', ['channel' => $channel, 'reason' => $e->getMessage()]);
            throw new BusinessException(lang('payment.unavailable'));
        }

        // datetime 列只到秒：用 time() 构造，保证传给网关的过期时间与库里是同一秒
        $expiresTimestamp = time() + $this->expireMinutes() * 60;
        $order = $this->insertOrder($prefix, [
            'user_id'      => $userId,
            'biz_type'     => $bizType,
            'client_type'  => $clientType,
            'channel'      => $channel,
            'app_id'       => $appId !== null && $appId !== '' ? $appId : null,
            'trade_type'   => $tradeType,
            'subject'      => $subject,
            'amount_cents' => $amountCents,
            'status'       => PaymentOrderRepository::STATUS_PENDING,
            'expires_at'   => date('Y-m-d H:i:s', $expiresTimestamp),
        ]);
        $orderNo = (string) $order['order_no'];

        try {
            $result = $gateway->create(new CreateOrderRequest(
                orderNo: $orderNo,
                tradeType: $tradeType,
                subject: $subject,
                amountCents: $amountCents,
                expiresAt: (new \DateTimeImmutable())->setTimestamp($expiresTimestamp),
                notifyUrl: $this->notifyUrl($channel),
                openid: $openid,
                clientIp: $clientIp,
                appId: $appId,
            ));
        } catch (GatewayException $e) {
            $this->orders->updateWhere(
                ['id' => (int) $order['id'], 'status' => PaymentOrderRepository::STATUS_PENDING],
                ['status' => PaymentOrderRepository::STATUS_CLOSED, 'closed_at' => date('Y-m-d H:i:s'), 'error_msg' => mb_substr($e->getMessage(), 0, 255)]
            );
            Log::warning('支付下单被渠道拒绝，订单已关闭', ['order_no' => $orderNo, 'channel' => $channel, 'reason' => $e->getMessage()]);
            throw new BusinessException(lang('payment.create_failed'));
        } catch (GatewayResultUnknownException $e) {
            // 渠道那边可能已经建单：保持 pending，交给查询补查与关单任务收尾
            Log::warning('支付下单结果不确定，订单保持待支付', ['order_no' => $orderNo, 'channel' => $channel, 'reason' => $e->getMessage()]);
            throw new BusinessException(lang('payment.create_failed'));
        }

        return [
            'order_no'     => $orderNo,
            'payment_id'   => (int) $order['id'],
            'payment_data' => ['trade_type' => $result->tradeType, 'data' => $result->data],
        ];
    }

    /**
     * 唯一的置已支付入口（spec §5.2）。一切判断都在行锁之后做。
     *
     * @param array<string, mixed> $raw 渠道原始数据（微信为解密后的 resource），存进 notify_data
     * @return string MarkPaidOutcome::*
     */
    public function markPaid(string $orderNo, string $channel, ?string $tradeNo, int $paidCents, array $raw): string
    {
        return $this->runInTransaction(function () use ($orderNo, $channel, $tradeNo, $paidCents, $raw): string {
            $order = $this->orders->findByOrderNoForUpdate($orderNo);
            if ($order === null) {
                Log::error('支付通知的订单不存在', ['order_no' => $orderNo, 'channel' => $channel]);

                return MarkPaidOutcome::NOT_FOUND;
            }

            $status = (string) $order['status'];
            if ($status === PaymentOrderRepository::STATUS_PAID || $status === PaymentOrderRepository::STATUS_REFUNDED) {
                return MarkPaidOutcome::ALREADY;
            }

            if ((string) $order['channel'] !== $channel || (int) $order['amount_cents'] !== $paidCents) {
                Log::error('支付通知与订单不符，拒绝置为已支付', [
                    'order_no'         => $orderNo,
                    'order_channel'    => $order['channel'],
                    'notify_channel'   => $channel,
                    'order_cents'      => (int) $order['amount_cents'],
                    'notify_cents'     => $paidCents,
                ]);

                return MarkPaidOutcome::MISMATCH;
            }

            if ($status === PaymentOrderRepository::STATUS_CLOSED) {
                Log::warning('已关闭的订单收到支付成功，照常入账', ['order_no' => $orderNo, 'channel' => $channel]);
            }

            // Repository::update() 走 Builder，模型 casts 不生效，JSON 列要自己编码
            $this->orders->update((int) $order['id'], [
                'status'      => PaymentOrderRepository::STATUS_PAID,
                'trade_no'    => $tradeNo,
                'paid_at'     => date('Y-m-d H:i:s'),
                'notify_data' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            ]);

            if ((string) $order['biz_type'] === PaymentOrderRepository::BIZ_RECHARGE) {
                $this->balanceService->change(
                    (int) $order['user_id'],
                    (float) Money::toYuan((int) $order['amount_cents']),
                    BalanceLogRepository::TYPE_RECHARGE,
                    'payment:' . $orderNo,
                    // 落库数据固定用中文，不随请求语言变化
                    lang('payment.recharge_remark', [], 'zh_CN')
                );
            }

            return MarkPaidOutcome::PAID;
        });
    }

    /**
     * 支付回调（spec §5.3）。永不抛出：任何失败都转成该渠道的失败应答，让渠道按自己的节奏重试。
     *
     * - 取网关不看开关（spec §5.8）：管理员关掉渠道后，在途订单的回调仍要入账。
     * - 只有 markPaid 真正接受（PAID）或幂等命中（ALREADY）才应答成功；MISMATCH / NOT_FOUND / 入账异常一律失败。
     * - 日志只记渠道、订单号与 ExceptionLogContext（非支付异常只记类名与异常码），不记回调原文与请求头（含签名与密文）。
     */
    public function handleNotify(string $channel, NotifyRequest $request): NotifyAck
    {
        try {
            $gateway = $this->gateways->gateway($channel);
        } catch (\Throwable $e) {
            Log::error('支付回调：网关不可用', ['channel' => $channel] + ExceptionLogContext::of($e));

            return $this->notifyFailureAck($channel);
        }

        try {
            $result = $gateway->verifyNotify($request);
        } catch (NotifyVerificationException $e) {
            Log::warning('支付回调：验签或核对失败', ['channel' => $channel, 'reason' => $e->getMessage()]);

            return $gateway->notifyAck(false);
        } catch (\Throwable $e) {
            Log::error('支付回调：验签过程异常', ['channel' => $channel] + ExceptionLogContext::of($e));

            return $gateway->notifyAck(false);
        }

        if (!$result->paid) {
            Log::info('支付回调：非支付成功事件，已应答不处理', ['channel' => $channel, 'order_no' => $result->orderNo]);

            return $gateway->notifyAck(true);
        }

        try {
            if ($channel === Channel::WECHAT && !$this->wechatAppIdMatchesOrder($result->orderNo, $result->appId)) {
                // appid 不是密钥：记下收到的与预期的，便于排查是哪个端/配置的 appid 对不上
                Log::error('支付回调：appid 与订单下单时不符，拒绝入账', [
                    'channel'         => $channel,
                    'order_no'        => $result->orderNo,
                    'appid'           => $result->appId,
                    'expected_appid'  => $this->wechatExpectedAppId($result->orderNo),
                ]);

                return $gateway->notifyAck(false);
            }
            // paidCents 缺失时传 -1：必然与订单金额不符，落到 MISMATCH，而不是含糊的 0
            $outcome = $this->markPaid($result->orderNo, $channel, $result->tradeNo, $result->paidCents ?? -1, $result->raw);
        } catch (\Throwable $e) {
            // 数据库异常的消息带 SQL 绑定值（回调原文）：经 ExceptionLogContext 只记类名与 SQLSTATE
            Log::error('支付回调：置已支付或入账失败，已回滚', [
                'channel'  => $channel,
                'order_no' => $result->orderNo,
            ] + ExceptionLogContext::of($e));

            return $gateway->notifyAck(false);
        }

        return $gateway->notifyAck($outcome === MarkPaidOutcome::PAID || $outcome === MarkPaidOutcome::ALREADY);
    }

    /**
     * 拿不到网关实例时的失败应答（凭据不全、私钥无效，或控制器兜底）。必须与各驱动 notifyAck(false) 逐字一致，
     * PaymentNotifyServiceTest::test_fallback_failure_ack_matches_real_drivers 钉住。
     */
    public function notifyFailureAck(string $channel): NotifyAck
    {
        return match ($channel) {
            Channel::WECHAT => new NotifyAck(500, 'application/json', '{"code":"FAIL","message":"失败"}'),
            Channel::ALIPAY => new NotifyAck(200, 'text/plain', 'fail'),
            default         => new NotifyAck(500, 'text/plain', 'fail'),
        };
    }

    /**
     * 微信已支付回调的 appid 必须是下单时用的那个（M6a spec §7）：订单记录了 app_id 就比它，
     * M6a 之前的旧订单没有 app_id 时回退 pay_wechat_app_id。订单不存在时放行，交给 markPaid 走 NOT_FOUND。
     * appid 下单后不变，锁外先查即可，不需要进 markPaid 的行锁。
     */
    private function wechatAppIdMatchesOrder(string $orderNo, ?string $appId): bool
    {
        $order = $this->orders->findByOrderNo($orderNo);
        if ($order === null) {
            return true;
        }
        $expected = trim((string) ($order['app_id'] ?? ''));
        if ($expected === '') {
            $expected = trim((string) $this->config->getConfigValue('pay_wechat_app_id', ''));
        }

        return $appId !== null && $appId !== '' && $expected !== '' && hash_equals($expected, $appId);
    }

    /**
     * 仅供不符日志使用：与 wechatAppIdMatchesOrder() 同样的「订单 app_id，空则回退 pay_wechat_app_id」口径，
     * 重新查一次订单——只在拒绝入账这条冷路径上跑，appid 不是密钥，记下来便于定位是哪个端/配置的 appid 对不上。
     */
    private function wechatExpectedAppId(string $orderNo): string
    {
        $order = $this->orders->findByOrderNo($orderNo);
        $expected = trim((string) ($order['app_id'] ?? ''));
        if ($expected === '') {
            $expected = trim((string) $this->config->getConfigValue('pay_wechat_app_id', ''));
        }

        return $expected;
    }

    /**
     * 查询本人订单（spec §5.4）。pending 时每单每个节流窗口最多补查一次网关；补查只吞网关层异常。
     *
     * @return array{order_no: string, status: string, amount: string, channel: string, paid_at: ?string}
     * @throws NotFoundException 非本人订单与不存在同一响应
     */
    public function queryForUser(string $orderNo, int $userId): array
    {
        $order = $this->orders->findForUser($orderNo, $userId) ?? throw new NotFoundException(lang('payment.order_not_found'));

        if ((string) $order['status'] === PaymentOrderRepository::STATUS_PENDING && $this->acquireQueryThrottle($orderNo)) {
            try {
                $result = $this->gateways->gateway((string) $order['channel'])->query($orderNo);
                if ($result->state === TradeQueryResult::PAID) {
                    // 这里不比 appid：查单是我们用 out_trade_no 在自己商户号下发起的已认证出站调用，结果不可能被伪造
                    // （appid 核对只挡回调路径）；因此补查也顺带把因 appid 不符被回调拒收的订单捞回来入账。
                    $this->markPaid($orderNo, (string) $order['channel'], $result->tradeNo, $result->paidCents ?? -1, $result->raw);
                    // 归属已在上面核过，按单号重读即可（同参再调 findForUser() 会被 PHPStan 沿用首行的非 null 收窄）
                    $order = $this->orders->findByOrderNo($orderNo) ?? $order;
                }
            } catch (PaymentException $e) {
                Log::info('订单补查失败，返回本地状态', ['order_no' => $orderNo, 'reason' => $e->getMessage()]);
            }
        }

        return [
            'order_no' => (string) $order['order_no'],
            'status'   => (string) $order['status'],
            'amount'   => Money::toYuan((int) $order['amount_cents']),
            'channel'  => (string) $order['channel'],
            'paid_at'  => $order['paid_at'] === null ? null : (string) $order['paid_at'],
        ];
    }

    /**
     * 关单任务（spec §5.5）。只扫 expires_at 早于「$now - 宽限」的待支付单，按过期时间升序，至多一批。
     * 每单独立 try/catch：一单出任何问题都只让它本轮跳过，下一轮再来；本方法不因单个订单抛出。
     *
     * @return array{scanned:int, paid:int, closed:int, skipped:int}
     */
    public function closeExpired(\DateTimeImmutable $now): array
    {
        $grace = max(0, (int) config('payment.close_grace_seconds', 60));
        $batch = max(1, (int) config('payment.close_batch', 100));
        $orders = $this->orders->findExpiredPending($now->sub(new \DateInterval("PT{$grace}S")), $batch);

        $counts = ['scanned' => count($orders), 'paid' => 0, 'closed' => 0, 'skipped' => 0];
        foreach ($orders as $order) {
            try {
                $counts[$this->closeOne($order, $now)]++;
            } catch (\Throwable $e) {
                $counts['skipped']++;
                Log::warning('关单：订单本轮跳过', ['order_no' => $order['order_no'] ?? null] + ExceptionLogContext::of($e));
            }
        }

        return $counts;
    }

    /**
     * 单个过期单：先问渠道。已支付 → 走 markPaid 补记入账（绝不关）；渠道侧已关 → 直接本地关；
     * 待支付或渠道无此单 → 先在渠道侧关掉（确保之后付不了）再本地关。
     *
     * @param array<string, mixed> $order
     * @return 'paid'|'closed'|'skipped'
     */
    private function closeOne(array $order, \DateTimeImmutable $now): string
    {
        $orderNo = (string) $order['order_no'];
        $channel = (string) $order['channel'];
        $gateway = $this->gateways->gateway($channel);
        $result = $gateway->query($orderNo);

        if ($result->state === TradeQueryResult::PAID) {
            // 同 queryForUser()：这是按我们的 out_trade_no、在我们商户号下发起的已认证出站查单，不可能被伪造，
            // 所以不比 appid；关单任务因此也会顺带把因 appid 不符被回调拒收的订单捞回来入账。
            $outcome = $this->markPaid($orderNo, $channel, $result->tradeNo, $result->paidCents ?? -1, $result->raw);

            return $outcome === MarkPaidOutcome::PAID ? 'paid' : 'skipped';
        }

        if ($result->state !== TradeQueryResult::CLOSED) {
            $gateway->close($orderNo);
        }

        return $this->closeLocally((int) $order['id'], $now) ? 'closed' : 'skipped';
    }

    /** 锁行后仍是 pending 才置 closed：关单请求在途时回调可能已经把它置成 paid，不能覆盖。 */
    private function closeLocally(int $orderId, \DateTimeImmutable $now): bool
    {
        return $this->runInTransaction(function () use ($orderId, $now): bool {
            $order = $this->orders->findForUpdate($orderId);
            if ($order === null || ($order['status'] ?? null) !== PaymentOrderRepository::STATUS_PENDING) {
                return false;
            }
            $this->orders->update($orderId, [
                'status'    => PaymentOrderRepository::STATUS_CLOSED,
                'closed_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return true;
        });
    }

    /**
     * @param array<string, mixed> $attributes 不含 order_no
     * @return array<string, mixed>
     */
    private function insertOrder(string $prefix, array $attributes): array
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return $this->orders->create(['order_no' => $this->orderNoGenerator->generate($prefix)] + $attributes);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_ORDER_NO_RETRIES) {
                    throw $e;
                }
            }
        }
    }

    /** 回调地址（spec §5.1 步骤 5）：配置优先，否则 site_url 拼默认路径。不看请求 Host 头。 */
    private function notifyUrl(string $channel): string
    {
        $configured = trim((string) $this->config->getConfigValue("pay_{$channel}_notify_url", ''));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim(trim((string) $this->config->getConfigValue('site_url', '')), '/') . '/api/payment/notify/' . $channel;
    }

    private function expireMinutes(): int
    {
        return max(1, (int) config('payment.order_expire_minutes', 30));
    }

    private function acquireQueryThrottle(string $orderNo): bool
    {
        $seconds = max(1, (int) config('payment.query_throttle_seconds', 10));

        return (bool) Redis::set("payment:query_throttle:{$orderNo}", '1', 'EX', $seconds, 'NX');
    }
}
