<?php

declare(strict_types=1);

namespace tests\fixtures\Queue;

use core\queue\QueueHandler;

/** 记录每次 handle() 收到的数据（静态数组：消费者是容器单例，测试用例之间手动清空）。 */
final class RecordingConsumer implements QueueHandler
{
    /** @var list<array<string, mixed>> */
    public static array $handled = [];

    public static function reset(): void
    {
        self::$handled = [];
    }

    public function handle(array $data): void
    {
        self::$handled[] = $data;
    }

    public function onConsumeFailure(\Throwable $e, array $package): array
    {
        return $package;
    }
}
