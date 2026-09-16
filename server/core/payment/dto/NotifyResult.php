<?php

declare(strict_types=1);

namespace core\payment\dto;

/** 验签通过后的回调内容。paid=false 表示不是支付成功事件：应答成功、不处理。 */
final readonly class NotifyResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public bool $paid,
        public string $orderNo = '',
        public ?string $tradeNo = null,
        public ?int $paidCents = null,
        public array $raw = [],
    ) {
    }
}
