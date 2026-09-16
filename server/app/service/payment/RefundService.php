<?php

declare(strict_types=1);

namespace app\service\payment;

use app\repository\payment\PaymentOrderRepository;
use app\repository\payment\RefundOrderRepository;
use app\repository\user\BalanceLogRepository;
use app\service\user\BalanceService;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\exception\PaymentConfigException;
use core\payment\GatewayResolver;
use core\payment\Money;
use core\payment\PaymentGatewayInterface;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Log;

/**
 * 退款（spec §5.6）与退款对账（spec §5.7，Task 12）。
 *
 * 两段事务夹一次网关调用：
 *   事务 1：锁订单 → 状态必须 paid → 同单无 processing → 不超额 → 充值单先扣余额 → 插 processing 退款单；
 *   事务外：调网关退款；
 *   事务 2：成功 settleSuccess()，明确失败 settleFailed()（充值单冲正），不确定保持 processing 交给对账。
 *
 * 加锁顺序固定为「订单行 → 退款单行 → 用户行」（设计决定 12），结算在锁内复查 processing（设计决定 13），
 * 所以命令与对账并发结算同一张退款单时不会重复冲正、也不会重复累加 refunded_cents。
 *
 * 容器单例，无实例态。
 */
class RefundService extends Service
{
    private const SOURCE_REFUND = 'refund:';

    private const SOURCE_REVERT = 'refund-revert:';

    /** 首次 + 撞唯一键后至多重生成 3 次（设计决定 10） */
    private const REFUND_NO_ATTEMPTS = 4;

    private const REASON_MAX_BYTES = 80;

    private const ERROR_MSG_MAX_CHARS = 255;

    /** 渠道明确失败但没给原因时写进 error_msg 的内部标识（不是对外文案，不走 lang） */
    private const ERROR_CHANNEL_FAILED = 'channel_refund_failed';

    /** 渠道查无此退款且已超过 refund_not_found_fail_seconds 时写进 error_msg 的内部标识 */
    private const ERROR_CHANNEL_NOT_FOUND = 'channel_refund_not_found';

    private const OUTCOME_SUCCESS = 'success';

    private const OUTCOME_FAILED = 'failed';

    private const OUTCOME_SKIPPED = 'skipped';

    #[Inject]
    protected PaymentOrderRepository $paymentOrderRepository;

    #[Inject]
    protected RefundOrderRepository $refundOrderRepository;

    #[Inject]
    protected BalanceService $balanceService;

    #[Inject]
    protected GatewayResolver $gateways;

    /** Task 7 的单号生成器，退款单号前缀 F（测试可换成排队返回撞号的子类） */
    #[Inject]
    protected OrderNoGenerator $orderNoGenerator;

    /**
     * @param string $amount   元，最多两位小数、大于 0（调用方负责校验格式，本方法只做防御性检查）
     * @param string $operator 执行者标识，命令行为 cli:{系统用户名}
     * @return array{refund_no: string, status: string, amount: string} status ∈ success|failed|processing
     * @throws BusinessException 订单不存在（NotFoundException）/ 状态不允许 / 有处理中 / 超额 / 余额不足 / 凭据不全
     */
    public function refund(string $orderNo, string $amount, string $reason, string $operator): array
    {
        $refundCents = Money::toCents($amount);
        if ($refundCents <= 0) {
            throw new \InvalidArgumentException('refund amount must be greater than 0');
        }
        $reason = mb_strcut($reason, 0, self::REASON_MAX_BYTES, 'UTF-8');

        // 先在事务外按订单渠道取网关：凭据不全时在扣余额之前就失败，不会出现「钱扣了却发不出退款」
        $order = $this->paymentOrderRepository->findByOrderNo($orderNo);
        if ($order === null) {
            throw new NotFoundException(lang('payment.order_not_found'));
        }
        $gateway = $this->resolveGateway((string) $order['channel']);

        $opened = $this->openRefund($orderNo, $refundCents, $reason, $operator);
        $refundId = (int) $opened['refund']['id'];
        $refundNo = (string) $opened['refund']['refund_no'];

        $result = $this->callGateway($gateway, new RefundRequest(
            $orderNo,
            $refundNo,
            $refundCents,
            (int) $opened['order']['amount_cents'],
            $reason,
        ));
        if ($result?->status === RefundResult::SUCCESS) {
            $this->settleSuccess($refundId, $result->channelRefundNo);
        } elseif ($result?->status === RefundResult::FAILED) {
            $this->settleFailed($refundId, $result->errorMsg ?? self::ERROR_CHANNEL_FAILED);
        }

        $final = $this->refundOrderRepository->find($refundId);

        return [
            'refund_no' => $refundNo,
            'status'    => (string) ($final['status'] ?? RefundOrderRepository::STATUS_PROCESSING),
            'amount'    => Money::toYuan($refundCents),
        ];
    }

    /** 渠道确认退款成功：锁内复查 processing，累加 refunded_cents，退满置 refunded。非 processing 直接返回。 */
    public function settleSuccess(int $refundId, ?string $channelRefundNo): void
    {
        $refund = $this->refundOrderRepository->find($refundId);
        if ($refund === null) {
            return;
        }

        $this->runInTransaction(function () use ($refund, $refundId, $channelRefundNo): void {
            $order = $this->paymentOrderRepository->findForUpdate((int) $refund['payment_order_id']);
            $locked = $this->refundOrderRepository->findForUpdate($refundId);
            if ($order === null || $locked === null || $locked['status'] !== RefundOrderRepository::STATUS_PROCESSING) {
                return;
            }

            $this->refundOrderRepository->update($refundId, [
                'status'            => RefundOrderRepository::STATUS_SUCCESS,
                'channel_refund_no' => $channelRefundNo,
                'refunded_at'       => date('Y-m-d H:i:s'),
            ]);

            $refundedCents = (int) $order['refunded_cents'] + (int) $locked['amount_cents'];
            $orderData = ['refunded_cents' => $refundedCents];
            if ($refundedCents >= (int) $order['amount_cents']) {
                $orderData['status'] = PaymentOrderRepository::STATUS_REFUNDED;
            }
            $this->paymentOrderRepository->update((int) $order['id'], $orderData);
        });
    }

    /** 渠道明确退款失败：锁内复查 processing，置 failed，充值单把事务 1 扣的余额加回。非 processing 直接返回。 */
    public function settleFailed(int $refundId, string $errorMsg): void
    {
        $refund = $this->refundOrderRepository->find($refundId);
        if ($refund === null) {
            return;
        }

        $this->runInTransaction(function () use ($refund, $refundId, $errorMsg): void {
            $order = $this->paymentOrderRepository->findForUpdate((int) $refund['payment_order_id']);
            $locked = $this->refundOrderRepository->findForUpdate($refundId);
            if ($order === null || $locked === null || $locked['status'] !== RefundOrderRepository::STATUS_PROCESSING) {
                return;
            }

            $this->refundOrderRepository->update($refundId, [
                'status'    => RefundOrderRepository::STATUS_FAILED,
                'error_msg' => mb_substr($errorMsg, 0, self::ERROR_MSG_MAX_CHARS),
            ]);

            if ($order['biz_type'] === PaymentOrderRepository::BIZ_RECHARGE) {
                $this->balanceService->change(
                    (int) $order['user_id'],
                    (float) Money::toYuan((int) $locked['amount_cents']),
                    BalanceLogRepository::TYPE_REFUND,
                    self::SOURCE_REVERT . $locked['refund_no'],
                    lang('payment.refund_revert_remark'),
                );
            }
        });
    }

    /**
     * 退款对账（spec §5.7）：扫描创建超过 reconcile_min_age_seconds 的 processing 退款单，逐条查网关结算。
     * 每条独立 try/catch，一条出错（网关不确定、凭据不全、数据库异常）只记日志并计入 skipped，不中断整轮。
     *
     * @return array{scanned: int, success: int, failed: int, skipped: int}
     */
    public function reconcile(\DateTimeImmutable $now): array
    {
        $minAge = (int) config('payment.reconcile_min_age_seconds', 120);
        $stuckAfter = (int) config('payment.refund_stuck_alert_seconds', 86400);
        $rows = $this->refundOrderRepository->findProcessingCreatedBefore(
            $now->modify("-{$minAge} seconds"),
            (int) config('payment.reconcile_batch', 100),
        );

        $counts = ['scanned' => count($rows), 'success' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($rows as $refund) {
            $age = $now->getTimestamp() - (new \DateTimeImmutable((string) $refund['created_at']))->getTimestamp();
            try {
                $outcome = $this->reconcileOne($refund, $age);
            } catch (\Throwable $e) {
                Log::warning('退款对账单条失败，下轮重试', ['refund_no' => $refund['refund_no'], 'error' => $e->getMessage()]);
                $outcome = self::OUTCOME_SKIPPED;
            }
            $counts[$outcome]++;

            if ($outcome === self::OUTCOME_SKIPPED && $age >= $stuckAfter) {
                Log::error('退款长时间处理中，需人工介入', [
                    'refund_no'  => $refund['refund_no'],
                    'created_at' => $refund['created_at'],
                ]);
            }
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $refund
     * @return self::OUTCOME_*
     */
    private function reconcileOne(array $refund, int $age): string
    {
        $order = $this->paymentOrderRepository->find((int) $refund['payment_order_id']);
        if ($order === null) {
            Log::error('退款单对应的支付订单不存在', ['refund_no' => $refund['refund_no']]);

            return self::OUTCOME_SKIPPED;
        }

        // 凭据不全抛 PaymentConfigException，由 reconcile() 的 catch 计入 skipped（查询与对账不看渠道开关，spec §5.8）
        $result = $this->gateways->gateway((string) $order['channel'])
            ->queryRefund((string) $order['order_no'], (string) $refund['refund_no']);

        switch ($result->status) {
            case RefundResult::SUCCESS:
                $this->settleSuccess((int) $refund['id'], $result->channelRefundNo);

                return self::OUTCOME_SUCCESS;
            case RefundResult::FAILED:
                $this->settleFailed((int) $refund['id'], $result->errorMsg ?? self::ERROR_CHANNEL_FAILED);

                return self::OUTCOME_FAILED;
            case RefundResult::NOT_FOUND:
                // 刚发出的退款可能还没在渠道落地：满阈值才判失败，防止把在途请求冲正掉
                if ($age >= (int) config('payment.refund_not_found_fail_seconds', 1800)) {
                    $this->settleFailed((int) $refund['id'], self::ERROR_CHANNEL_NOT_FOUND);

                    return self::OUTCOME_FAILED;
                }

                return self::OUTCOME_SKIPPED;
            default:
                return self::OUTCOME_SKIPPED;
        }
    }

    /**
     * 事务 1。撞退款单号唯一键时连同扣款整体回滚、换号重来：余额流水的 source 里带着退款单号。
     *
     * @return array{order: array<string, mixed>, refund: array<string, mixed>}
     */
    private function openRefund(string $orderNo, int $refundCents, string $reason, string $operator): array
    {
        for ($attempt = 1; ; $attempt++) {
            $refundNo = $this->orderNoGenerator->generate('F');
            try {
                return $this->runInTransaction(function () use ($orderNo, $refundCents, $reason, $operator, $refundNo): array {
                    $order = $this->paymentOrderRepository->findByOrderNoForUpdate($orderNo);
                    if ($order === null) {
                        throw new NotFoundException(lang('payment.order_not_found'));
                    }
                    if ($order['status'] !== PaymentOrderRepository::STATUS_PAID) {
                        throw new BusinessException(lang('payment.refund_status_invalid'));
                    }
                    // 订单行锁已持有：同一订单的退款在这里串行，看得到前一个事务已提交的 processing 单
                    if ($this->refundOrderRepository->hasProcessing((int) $order['id'])) {
                        throw new BusinessException(lang('payment.refund_in_progress'));
                    }
                    if ((int) $order['refunded_cents'] + $refundCents > (int) $order['amount_cents']) {
                        throw new BusinessException(lang('payment.refund_exceeds'));
                    }

                    if ($order['biz_type'] === PaymentOrderRepository::BIZ_RECHARGE) {
                        try {
                            $this->balanceService->change(
                                (int) $order['user_id'],
                                -(float) Money::toYuan($refundCents),
                                BalanceLogRepository::TYPE_REFUND,
                                self::SOURCE_REFUND . $refundNo,
                                lang('payment.refund_remark'),
                            );
                        } catch (ValidationException) {
                            // BalanceService 结果为负抛 422（errors.amount）；命令行场景换成明确文案，整个事务回滚
                            throw new BusinessException(lang('payment.refund_balance_insufficient'));
                        }
                    }

                    $refund = $this->refundOrderRepository->create([
                        'refund_no'        => $refundNo,
                        'payment_order_id' => (int) $order['id'],
                        'amount_cents'     => $refundCents,
                        'reason'           => $reason,
                        'status'           => RefundOrderRepository::STATUS_PROCESSING,
                        'operator'         => $operator,
                    ]);

                    return ['order' => $order, 'refund' => $refund];
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::REFUND_NO_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function resolveGateway(string $channel): PaymentGatewayInterface
    {
        try {
            return $this->gateways->gateway($channel);
        } catch (PaymentConfigException $e) {
            Log::warning('退款失败：支付渠道凭据不全或无效', ['channel' => $channel, 'error' => $e->getMessage()]);

            throw new BusinessException(lang('payment.unavailable'));
        }
    }

    /** 网关调用。结果不确定（含任何意外异常）返回 null：退款单保持 processing，交给对账，绝不冲正。 */
    private function callGateway(PaymentGatewayInterface $gateway, RefundRequest $request): ?RefundResult
    {
        try {
            return $gateway->refund($request);
        } catch (GatewayResultUnknownException $e) {
            Log::warning('退款结果不确定，等待对账', ['refund_no' => $request->refundNo, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('退款网关调用异常，按结果不确定处理', ['refund_no' => $request->refundNo, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
