<?php
declare(strict_types=1);

namespace app\model\payment;

use core\base\Model;

class PaymentOrder extends Model
{
    protected $table = 'payment_orders';

    protected $fillable = [
        'user_id', 'biz_type',
        'order_no', 'trade_no', 'channel', 'trade_type',
        'subject', 'body', 'total_amount', 'refund_amount',
        'status', 'notify_data', 'extra',
        'paid_at', 'refunded_at',
    ];

    protected $type = [
        'user_id'       => 'integer',
        'total_amount'  => 'float',
        'refund_amount' => 'float',
    ];

    // 状态常量
    const STATUS_PENDING  = 'pending';
    const STATUS_PAID     = 'paid';
    const STATUS_CLOSED   = 'closed';
    const STATUS_REFUNDED = 'refunded';

    /**
     * 根据商户订单号查找
     */
    public static function findByOrderNo(string $orderNo): ?static
    {
        return static::where('order_no', $orderNo)->find();
    }
}
