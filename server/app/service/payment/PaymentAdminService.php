<?php

declare(strict_types=1);

namespace app\service\payment;

use app\repository\payment\PaymentOrderRepository;
use app\repository\payment\RefundOrderRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\exception\NotFoundException;
use core\payment\Money;
use DI\Attribute\Inject;

/** 管理端支付订单只读整形 + 退款入口（写路径仍是 RefundService）。 */
class PaymentAdminService extends Service
{
    #[Inject]
    protected PaymentOrderRepository $paymentOrderRepository;

    #[Inject]
    protected RefundOrderRepository $refundOrderRepository;

    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected RefundService $refundService;

    /**
     * @param array<string, mixed> $params
     * @return array{list: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        $result = $this->paymentOrderRepository->getAdminList($params, $page, $limit);
        $users = $this->userRepository->briefsByIds(array_map(
            static fn (array $row): int => (int) ($row['user_id'] ?? 0),
            $result['list']
        ));
        $result['list'] = array_map(fn (array $row): array => $this->presentOrder($row, $users), $result['list']);

        return $result;
    }

    /** @return array<string, mixed> */
    public function getDetail(string $orderNo): array
    {
        $order = $this->paymentOrderRepository->findByOrderNo($orderNo);
        if ($order === null) {
            throw new NotFoundException(lang('payment.order_not_found'));
        }
        $users = $this->userRepository->briefsByIds([(int) $order['user_id']]);
        $presented = $this->presentOrder($order, $users);
        $presented['refunds'] = array_map(
            fn (array $row): array => $this->presentRefund($row),
            $this->refundOrderRepository->listByPaymentOrderId((int) $order['id'])
        );

        return $presented;
    }

    /**
     * @return array{refund_no: string, status: string, amount: string}
     */
    public function refund(string $orderNo, string $amount, string $reason, int $adminId): array
    {
        return $this->refundService->refund($orderNo, $amount, $reason, 'admin:' . $adminId);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array{nickname: string, mobile: string}> $users
     * @return array<string, mixed>
     */
    private function presentOrder(array $row, array $users): array
    {
        $userId = (int) ($row['user_id'] ?? 0);

        return [
            'id'              => (int) $row['id'],
            'order_no'        => (string) $row['order_no'],
            'user_id'         => $userId,
            'user_nickname'   => $users[$userId]['nickname'] ?? '',
            'user_mobile'     => $users[$userId]['mobile'] ?? '',
            'biz_type'        => (string) $row['biz_type'],
            'channel'         => (string) $row['channel'],
            'trade_type'      => (string) $row['trade_type'],
            'subject'         => (string) $row['subject'],
            'amount'          => Money::toYuan((int) $row['amount_cents']),
            'refunded_amount' => Money::toYuan((int) $row['refunded_cents']),
            'status'          => (string) $row['status'],
            'trade_no'        => (string) ($row['trade_no'] ?? ''),
            'paid_at'         => (string) ($row['paid_at'] ?? ''),
            'created_at'      => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentRefund(array $row): array
    {
        return [
            'id'                => (int) $row['id'],
            'refund_no'         => (string) $row['refund_no'],
            'amount'            => Money::toYuan((int) $row['amount_cents']),
            'reason'            => (string) ($row['reason'] ?? ''),
            'status'            => (string) $row['status'],
            'channel_refund_no' => (string) ($row['channel_refund_no'] ?? ''),
            'operator'          => (string) ($row['operator'] ?? ''),
            'created_at'        => (string) ($row['created_at'] ?? ''),
        ];
    }
}
