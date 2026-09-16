<?php

declare(strict_types=1);

namespace app\repository\payment;

use app\model\payment\RefundOrder;
use core\base\Model;
use core\base\Repository;

/**
 * 退款单仓储（M5b spec §8）：不设 $dataScoped——refund_orders 没有 created_by 也没有部门列，
 * 退款只有命令行入口，不存在按管理员范围过滤的场景；红线 Test26 钉住。
 */
class RefundOrderRepository extends Repository
{
    /** 转发常量：Service 禁止引用 app\model\* 的常量（check:context 规则三），经这里取值。 */
    public const STATUS_PROCESSING = RefundOrder::STATUS_PROCESSING;

    public const STATUS_SUCCESS = RefundOrder::STATUS_SUCCESS;

    public const STATUS_FAILED = RefundOrder::STATUS_FAILED;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at'];

    protected function getModel(): Model
    {
        return new RefundOrder();
    }

    /** @return array<string, mixed>|null */
    public function findByRefundNo(string $refundNo): ?array
    {
        return $this->query()->where($this->qualify('refund_no'), $refundNo)->first()?->toArray();
    }

    /**
     * 事务内行锁读。lockForUpdate() 单独成句的原因见 UserRepository::findForUpdate()。
     *
     * @return array<string, mixed>|null
     */
    public function findForUpdate(int $id): ?array
    {
        $query = $this->query()->where($this->qualify('id'), $id);
        $query->lockForUpdate();
        $row = $query->first();

        return $row === null ? null : $row->toArray();
    }

    /** 同一订单是否存在处理中的退款（退款串行化，M5b spec §5.6 事务 1 第 2 步）。 */
    public function hasProcessing(int $paymentOrderId): bool
    {
        return $this->query()
            ->where($this->qualify('payment_order_id'), $paymentOrderId)
            ->where($this->qualify('status'), self::STATUS_PROCESSING)
            ->exists();
    }

    /**
     * 对账任务：创建早于 $before 仍在处理中的退款单，按创建时间升序。
     *
     * @return list<array<string, mixed>>
     */
    public function findProcessingCreatedBefore(\DateTimeImmutable $before, int $limit): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->query()
            ->where($this->qualify('status'), self::STATUS_PROCESSING)
            ->where($this->qualify('created_at'), '<', $before->format('Y-m-d H:i:s'))
            ->orderBy($this->qualify('created_at'))
            ->orderBy($this->qualify('id'))
            ->limit(max(1, $limit))
            ->get()
            ->toArray();

        return array_values($rows);
    }
}
