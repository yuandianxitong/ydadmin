<?php

declare(strict_types=1);

namespace app\repository\payment;

use app\model\payment\PaymentOrder;
use core\base\Model;
use core\base\Repository;

/**
 * 支付订单仓储（M5b spec §8）：不设 $dataScoped——payment_orders 没有 created_by 也没有部门列。
 * C 端的归属隔离靠 findForUser() 显式带 user_id，不靠数据范围；红线 Test26 钉住。
 */
class PaymentOrderRepository extends Repository
{
    /** 转发常量：Service 禁止引用 app\model\* 的常量（check:context 规则三），经这里取值。 */
    public const STATUS_PENDING = PaymentOrder::STATUS_PENDING;

    public const STATUS_PAID = PaymentOrder::STATUS_PAID;

    public const STATUS_CLOSED = PaymentOrder::STATUS_CLOSED;

    public const STATUS_REFUNDED = PaymentOrder::STATUS_REFUNDED;

    public const BIZ_RECHARGE = PaymentOrder::BIZ_RECHARGE;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'expires_at'];

    protected function getModel(): Model
    {
        return new PaymentOrder();
    }

    /** @return array<string, mixed>|null */
    public function findByOrderNo(string $orderNo): ?array
    {
        return $this->query()->where($this->qualify('order_no'), $orderNo)->first()?->toArray();
    }

    /**
     * 事务内按订单号行锁读。lockForUpdate() 单独成句的原因见 UserRepository::findForUpdate()。
     *
     * @return array<string, mixed>|null
     */
    public function findByOrderNoForUpdate(string $orderNo): ?array
    {
        $query = $this->query()->where($this->qualify('order_no'), $orderNo);
        $query->lockForUpdate();
        $row = $query->first();

        return $row === null ? null : $row->toArray();
    }

    /**
     * 事务内按 id 行锁读（退款结算按退款单上的 payment_order_id 回锁订单）。
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

    /**
     * C 端查询：只查本人订单。他人订单与不存在一律返回 null，调用方统一报「订单不存在」。
     *
     * @return array<string, mixed>|null
     */
    public function findForUser(string $orderNo, int $userId): ?array
    {
        return $this->query()
            ->where($this->qualify('order_no'), $orderNo)
            ->where($this->qualify('user_id'), $userId)
            ->first()?->toArray();
    }

    /**
     * 关单任务：截止时间早于 $before 的待支付订单，按截止时间升序（最早过期的先处理）。
     *
     * @return list<array<string, mixed>>
     */
    public function findExpiredPending(\DateTimeImmutable $before, int $limit): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->query()
            ->where($this->qualify('status'), self::STATUS_PENDING)
            ->where($this->qualify('expires_at'), '<', $before->format('Y-m-d H:i:s'))
            ->orderBy($this->qualify('expires_at'))
            ->orderBy($this->qualify('id'))
            ->limit(max(1, $limit))
            ->get()
            ->toArray();

        return array_values($rows);
    }
}
