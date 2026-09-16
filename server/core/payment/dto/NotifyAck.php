<?php

declare(strict_types=1);

namespace core\payment\dto;

/** 回调应答（M5b spec §5.3）：控制器原样写成 HTTP 响应，不走统一响应体。 */
final readonly class NotifyAck
{
    public function __construct(
        public int $status,
        public string $contentType,
        public string $body,
    ) {
    }
}
