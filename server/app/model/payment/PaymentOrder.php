<?php

declare(strict_types=1);

namespace app\model\payment;

use core\base\Model;

/**
 * 支付订单（payment_orders 表，M5b spec §2.1）。无软删。
 * 金额一律整数分（amount_cents / refunded_cents），元字符串的转换只经 core\payment\Money。
 * 状态机：pending →(markPaid) paid →(累计退满) refunded；pending →(关单) closed →(markPaid，钱确实收到) paid。
 *
 * Service/Controller 禁止引用本类常量（check:context 规则三），经 PaymentOrderRepository 的转发常量取值。
 */
class PaymentOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REFUNDED = 'refunded';

    public const BIZ_RECHARGE = 'recharge';

    protected $table = 'payment_orders';

    /** @var array<string, string> */
    protected $casts = [
        'user_id'        => 'integer',
        'amount_cents'   => 'integer',
        'refunded_cents' => 'integer',
        'notify_data'    => 'array',
    ];
}
