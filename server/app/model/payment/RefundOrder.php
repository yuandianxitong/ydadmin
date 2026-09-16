<?php

declare(strict_types=1);

namespace app\model\payment;

use core\base\Model;

/**
 * 退款单（refund_orders 表，M5b spec §2.2）。无软删。
 * refund_no 同时作为微信 out_refund_no / 支付宝 out_request_no，每次退款独立一个单号。
 *
 * Service/Controller 禁止引用本类常量（check:context 规则三），经 RefundOrderRepository 的转发常量取值。
 */
class RefundOrder extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $table = 'refund_orders';

    /** @var array<string, string> */
    protected $casts = [
        'payment_order_id' => 'integer',
        'amount_cents'     => 'integer',
    ];
}
