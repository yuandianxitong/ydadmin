<?php

declare(strict_types=1);

namespace app\queue\redis_tasks;

use app\queue\ConsumerBase;
use app\service\operations\TaskService;
use core\queue\PermanentJobFailure;
use DI\Attribute\Inject;

final class BackgroundTaskConsumer extends ConsumerBase
{
    public string $queue = 'background-task';

    #[Inject]
    protected TaskService $tasks;

    public function handle(array $data): void
    {
        $this->tasks->execute((int) ($data['task_id'] ?? 0));
    }

    public function onConsumeFailure(\Throwable $e, array $package): array
    {
        if ($e instanceof PermanentJobFailure || (int) ($package['attempts'] ?? 0) + 1 > (int) config('queue.queues.background-task.max_attempts', 3)) {
            $this->tasks->markFailed((int) ($package['data']['task_id'] ?? 0));
        }

        return parent::onConsumeFailure($e, $package);
    }
}
