<?php

declare(strict_types=1);

namespace core\queue;

use support\Container;
use support\Log;
use Webman\RedisQueue\Redis as QueueRedis;

/**
 * 队列统一投递入口（M3，spec §5）。容器单例，没有实例状态。
 *
 * - redis 驱动（默认）：webman/redis-queue 的同步客户端把包裹写进 Redis，由 queue 进程消费。连接从协程连接池借出并
 *   登记在 Context 上，请求结束随 Context::destroy() 归还——默认 select 事件循环下已实测可用（计划设计决定 1）。
 * - sync 驱动（测试专用，tests/bootstrap.php 强制）：投递即在当前进程调用消费者的 handle()。不调 consume()：
 *   consume() 会 Context::destroy()，同步调用会把当次请求的管理员身份、语言、数据范围抹掉（设计决定 3）。
 *   失败不重试：包裹的 attempts 直接等于 max_attempts，交给 onConsumeFailure() 按「最后一次失败」处理，异常不外抛（设计决定 5）。
 *   注意：sync 下消费者跑在当次请求的上下文里（含管理员数据范围），真实 queue 进程里没有上下文——只影响测试（设计决定 7）。
 *
 * 两个驱动都先查 config/queue.php 的登记：未登记的队列一律拒绝，避免拼错队列名的任务静默堆在 Redis 里没人消费。
 */
class QueueDispatcher
{
    /**
     * @param array<string, mixed> $data 必须可 JSON 编码（redis 驱动写 Redis 前编码，sync 驱动同样往返一次保持一致）
     * @throws \InvalidArgumentException 队列未在 config/queue.php 登记，或 sync 驱动下消费者没有实现 QueueHandler
     * @throws \RuntimeException redis 驱动投递失败（Redis 不可用或未确认写入）
     */
    public function dispatch(string $queue, array $data): void
    {
        $definition = $this->definition($queue);

        if ((string) config('queue.driver', 'redis') === 'sync') {
            $this->dispatchSync($queue, $definition, $data);

            return;
        }

        try {
            $sent = QueueRedis::connection()->send($queue, $data);
        } catch (\Throwable $e) {
            throw new \RuntimeException("队列 {$queue} 投递失败：" . $e->getMessage(), 0, $e);
        }
        if ($sent !== true) {
            throw new \RuntimeException("队列 {$queue} 投递失败：Redis 未确认写入");
        }
    }

    /** @return array{consumer: string, max_attempts: int} */
    private function definition(string $queue): array
    {
        $definition = config("queue.queues.{$queue}");
        if (!is_array($definition) || !is_string($definition['consumer'] ?? null) || $definition['consumer'] === '') {
            throw new \InvalidArgumentException("队列 {$queue} 未在 config/queue.php 登记");
        }

        return [
            'consumer'     => $definition['consumer'],
            'max_attempts' => max(0, (int) ($definition['max_attempts'] ?? 0)),
        ];
    }

    /**
     * @param array{consumer: string, max_attempts: int} $definition
     * @param array<string, mixed> $data
     */
    private function dispatchSync(string $queue, array $definition, array $data): void
    {
        $consumer = Container::get($definition['consumer']);
        if (!$consumer instanceof QueueHandler) {
            throw new \InvalidArgumentException("队列 {$queue} 的消费者 {$definition['consumer']} 没有实现 " . QueueHandler::class);
        }

        // 与 redis 驱动一致：消费者拿到的是 JSON 往返后的数据（对象、资源等不可编码的值在这里就暴露出来）
        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), true);

        try {
            $consumer->handle($payload);
        } catch (\Throwable $e) {
            Log::error("队列 {$queue} 同步消费失败：" . $e->getMessage(), ['queue' => $queue]);
            $package = [
                'id'           => time() . random_int(1000, 9999),
                'time'         => time(),
                'delay'        => 0,
                'attempts'     => $definition['max_attempts'],
                'queue'        => $queue,
                'data'         => $payload,
                'max_attempts' => $definition['max_attempts'],
                'error'        => $e->getMessage(),
            ];
            try {
                $consumer->onConsumeFailure($e, $package);
            } catch (\Throwable $failure) {
                Log::error("队列 {$queue} 失败回调本身抛出异常：" . $failure->getMessage(), ['queue' => $queue]);
            }
        }
    }
}
