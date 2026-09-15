<?php

declare(strict_types=1);

namespace tests\fixtures\Realtime;

/** 握手请求替身：WebSocketServer 只调用 get('ticket')。 */
final class FakeHandshakeRequest
{
    /** @param array<string, string> $query */
    public function __construct(private readonly array $query)
    {
    }

    public function get(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->query;
        }

        return $this->query[$name] ?? $default;
    }
}
