<?php

declare(strict_types=1);

namespace tests\fixtures\Queue;

use core\queue\QueueHandler;

/** handle() 恒抛异常；onConsumeFailure() 记下收到的异常消息与包裹。 */
final class ThrowingConsumer implements QueueHandler
{
    /** @var list<array{message: string, package: array<string, mixed>}> */
    public static array $failures = [];

    public function handle(array $data): void
    {
        throw new \RuntimeException('boom');
    }

    public function onConsumeFailure(\Throwable $e, array $package): array
    {
        self::$failures[] = ['message' => $e->getMessage(), 'package' => $package];

        return $package;
    }
}
