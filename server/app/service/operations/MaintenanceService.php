<?php

declare(strict_types=1);

namespace app\service\operations;

use app\repository\operations\IdempotencyRepository;
use app\repository\operations\OutboxRepository;
use core\base\Service;
use DI\Attribute\Inject;

final class MaintenanceService extends Service
{
    #[Inject]
    protected IdempotencyRepository $idempotency;
    #[Inject]
    protected OutboxRepository $outbox;
    #[Inject]
    protected TaskService $tasks;

    /**
     * @return array<string, int> */
    public function cleanup(): array
    {
        return ['tasks' => $this->tasks->cleanup(), 'idempotency' => $this->idempotency->purge(),
            'deliveries' => $this->outbox->purge(time() - (int) config('operations.task_retention_seconds'))];
    }
}
