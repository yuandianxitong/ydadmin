<?php

declare(strict_types=1);

namespace app\queue\redis;

use app\queue\ConsumerBase;
use app\repository\system\AdminOperationLogRepository;
use DI\Attribute\Inject;

/**
 * 操作日志消费者（spec §8.4）：AdminLogMiddleware 在请求内算好载荷（文案按当次语言解析、参数已脱敏）投递到
 * operation-log 队列，这里只负责落库。失败由 redis-queue 重试（config/queue.php 的 max_attempts = 3，
 * 间隔为全局 retry_seconds × 次数），仍失败由 ConsumerBase 写 failed_jobs。
 *
 * 队列进程里没有管理员上下文，受控仓储不套数据权限（spec §5.3）；record() 走 create()，本就不经作用域。
 */
final class OperationLogConsumer extends ConsumerBase
{
    public string $queue = 'operation-log';

    #[Inject]
    protected AdminOperationLogRepository $operationLogRepository;

    public function handle(array $data): void
    {
        $this->operationLogRepository->record($data);
    }
}
