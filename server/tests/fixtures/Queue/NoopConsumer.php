<?php

declare(strict_types=1);

namespace tests\fixtures\Queue;

use core\queue\QueueHandler;

/**
 * 什么也不做的消费者（计划设计决定 8）。测试里用 ConfigOverride 把某个队列的 consumer 临时指向它，
 * 模拟「消息已投递、但没有消费者处理」——例如手动执行时队列进程没启动，http 进程的 BLPOP 必然超时。
 */
final class NoopConsumer implements QueueHandler
{
    public function handle(array $data): void
    {
    }

    public function onConsumeFailure(\Throwable $e, array $package): array
    {
        return $package;
    }
}
