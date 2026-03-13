<?php
declare(strict_types=1);

namespace app\repository\payment;

use app\model\payment\PaymentOrder;
use core\base\Repository;
use think\Model;

class PaymentOrderRepository extends Repository
{
    protected function getModel(): Model
    {
        return new PaymentOrder();
    }

    /**
     * 根据订单号查找
     */
    public function findByOrderNo(string $orderNo): ?Model
    {
        return PaymentOrder::findByOrderNo($orderNo);
    }

    /**
     * 根据订单号查找并加行锁（用于回调处理）
     */
    public function findByOrderNoForUpdate(string $orderNo): ?Model
    {
        return $this->model->where('order_no', $orderNo)->lock(true)->find();
    }

    /**
     * 创建支付订单并返回模型实例
     */
    public function createOrder(array $data): Model
    {
        return PaymentOrder::create($data);
    }
}
