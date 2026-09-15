<?php

declare(strict_types=1);

namespace core\queue;

/**
 * 队列消费者契约（M3）。app\queue\ConsumerBase 实现了它；QueueDispatcher 的 sync 驱动只认这个接口。
 *
 * redis-queue 的消费进程调 consume()（ConsumerBase 在其中清理 Context）；sync 驱动直接调 handle()（不清理）。
 */
interface QueueHandler
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): void;

    /**
     * redis-queue 消费失败回调（与库的回调同名同参）。库在调用它之后才执行 ++attempts > max_attempts，
     * 所以「这是最后一次失败」的判定是 attempts + 1 > max_attempts。返回包裹：至少写回 max_attempts。
     *
     * @param array<string, mixed> $package {id, time, delay, attempts, queue, data, max_attempts?, error?}
     * @return array<string, mixed>
     */
    public function onConsumeFailure(\Throwable $e, array $package): array;
}
