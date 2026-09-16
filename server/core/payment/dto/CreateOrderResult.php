<?php

declare(strict_types=1);

namespace core\payment\dto;

/** 网关下单结果：data 原样作为 C 端响应的 payment_data.data（形状见计划「驱动」一节）。 */
final readonly class CreateOrderResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $tradeType,
        public array $data,
    ) {
    }
}
