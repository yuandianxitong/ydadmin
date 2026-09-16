<?php

declare(strict_types=1);

namespace core\payment\dto;

/** 退款或退款查询结果（计划设计决定 4）。NOT_FOUND 只由 queryRefund() 返回。 */
final readonly class RefundResult
{
    public const SUCCESS = 'success';

    public const PROCESSING = 'processing';

    public const FAILED = 'failed';

    public const NOT_FOUND = 'not_found';

    public function __construct(
        public string $status,
        public ?string $channelRefundNo = null,
        public ?string $errorMsg = null,
    ) {
    }
}
