<?php

declare(strict_types=1);

namespace app\service\wechat\dto;

/** 公众号 serve 应答：控制器原样写成 HTTP 响应，不走统一响应体。 */
final readonly class ServeAck
{
    public function __construct(
        public int $status,
        public string $contentType,
        public string $body,
    ) {
    }
}
