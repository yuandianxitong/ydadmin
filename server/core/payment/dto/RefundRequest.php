<?php

declare(strict_types=1);

namespace core\payment\dto;

/** 网关退款请求。totalCents 是原订单实付（微信退款接口必填）。 */
final readonly class RefundRequest
{
    public function __construct(
        public string $orderNo,
        public string $refundNo,
        public int $refundCents,
        public int $totalCents,
        public string $reason,
    ) {
    }
}
