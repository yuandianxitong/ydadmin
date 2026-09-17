<?php

declare(strict_types=1);

namespace app\queue;

use app\service\system\FailedJobService;
use core\queue\QueueHandler;
use DI\Attribute\Inject;
use support\Context;
use support\Log;
use Webman\RedisQueue\Consumer;

/**
 * 队列消费者基类（M3，计划设计决定 2–5）。子类声明 $queue 并实现 handle()，放在 app/queue/redis/ 或 app/queue/redis_slow/ 下
 * （按耗时分组，见 config/plugin/webman/redis-queue/process.php）。
 *
 * 本类是抽象类，必须留在 app/queue/，不能放进这两个子目录：消费进程会对目录里每个实现了
 * Webman\RedisQueue\Consumer 的类 Container::get()，抽象类会让进程启动即崩。
 *
 * - consume()：redis-queue 消费进程的入口。每个任务结束（成功或抛异常）都 Context::destroy()——自定义进程不像
 *   http 请求那样按次重置 Context，不清的话上一个任务设置的操作人、数据范围会串到下一个任务
 *   （关闭 M1 延续清单「M3 队列：每个任务开始前重置 support\Context」）。异常原样抛给库，由库决定重试。
 * - onConsumeFailure()：把本队列登记的 max_attempts 写回包裹（重试间隔是全局的，包裹里改不了）；
 *   库在回调之后才 ++attempts，所以 attempts + 1 > max_attempts 即最后一次失败，此时写 failed_jobs。
 *
 * 容器单例：不在实例属性里存任务态。
 */
abstract class ConsumerBase implements Consumer, QueueHandler
{
    /** 队列名：子类覆盖。redis-queue 消费进程读这个公开属性决定订阅哪个队列。 */
    public string $queue = '';

    /** redis-queue 连接名（config/plugin/webman/redis-queue/redis.php 的键）。 */
    public string $connection = 'default';

    #[Inject]
    protected FailedJobService $failedJobService;

    final public function consume(mixed $data): void
    {
        try {
            $this->handle(is_array($data) ? $data : []);
        } finally {
            Context::destroy();
        }
    }

    public function onConsumeFailure(\Throwable $e, array $package): array
    {
        $queue = (string) ($package['queue'] ?? $this->queue);
        $maxAttempts = max(0, (int) config("queue.queues.{$queue}.max_attempts", 0));
        $package['max_attempts'] = $maxAttempts;
        $attempts = (int) ($package['attempts'] ?? 0) + 1;

        Log::error("队列 {$queue} 第 {$attempts} 次消费失败：" . $e->getMessage(), ['queue' => $queue]);

        if ($attempts > $maxAttempts) {
            $data = $package['data'] ?? [];
            try {
                $this->failedJobService->record($queue, is_array($data) ? $data : [], $e, $attempts);
            } catch (\Throwable $recordError) {
                // 落表失败不能让回调抛出：库会把包裹推进 Redis 的失败列表，至少那里还有一份
                Log::error("队列 {$queue} 的失败任务写入 failed_jobs 失败：" . $recordError->getMessage(), ['queue' => $queue]);
            }
        }

        return $package;
    }
}
