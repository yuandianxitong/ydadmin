<?php

declare(strict_types=1);

namespace core\realtime;

use support\Log;
use support\Redis;

/**
 * 发布实时消息（spec §5、§7「投递」）：同步 PUBLISH 到 realtime:admin，HTTP / 队列 / 命令行进程都可调用。
 *
 * 推送只是加速——通知以数据库为准、强制下线有 token 版本号兜底——所以任何失败（非法事件、编码失败、
 * Redis 不可用）都只记 warning，绝不冒出去影响业务请求。
 * 非 final、无构造参数：测试继承覆盖 publishRaw()。容器单例，无状态。
 */
class RealtimePublisher
{
    public const CHANNEL = 'realtime:admin';

    /**
     * @param 'all'|list<int> $targets
     * @param array<string, mixed> $payload
     */
    public function publish(string|array $targets, string $event, array $payload): void
    {
        try {
            $this->publishRaw(self::CHANNEL, (new RealtimeMessage($targets, $event, $payload))->encode());
        } catch (\Throwable $e) {
            Log::warning('实时推送失败：' . $e->getMessage(), ['event' => $event]);
        }
    }

    /** 任务进度（spec §6 task.progress）：本期只提供通道，暂无生产方。percent 夹到 0–100。 */
    public function progress(int $adminId, string $taskId, int $percent, string $message): void
    {
        $this->publish([$adminId], 'task.progress', [
            'task_id' => $taskId,
            'percent' => max(0, min(100, $percent)),
            'message' => $message,
        ]);
    }

    protected function publishRaw(string $channel, string $message): void
    {
        Redis::publish($channel, $message);
    }
}
