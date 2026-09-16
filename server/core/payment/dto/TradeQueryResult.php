<?php

declare(strict_types=1);

namespace core\payment\dto;

/** 查单结果四态（计划设计决定 5）。raw 为渠道原始应答，只进日志与 notify_data，不下发客户端。 */
final readonly class TradeQueryResult
{
    public const PAID = 'paid';

    public const PENDING = 'pending';

    public const CLOSED = 'closed';

    public const NOT_FOUND = 'not_found';

    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $state,
        public ?string $tradeNo = null,
        public ?int $paidCents = null,
        public array $raw = [],
    ) {
    }
}
