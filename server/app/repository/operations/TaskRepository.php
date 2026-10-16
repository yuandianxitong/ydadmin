<?php

declare(strict_types=1);

namespace app\repository\operations;

use app\model\operations\BackgroundTask;
use core\base\Model;
use core\base\Repository;

final class TaskRepository extends Repository
{
    protected function getModel(): Model
    {
        return new BackgroundTask();
    }

    /**
     * @return array<string, mixed>|null */
    public function owned(string $publicId, int $adminId): ?array
    {
        return $this->query()->where('public_id', $publicId)->where('admin_id', $adminId)->first()?->toArray();
    }

    /**
     * @return array<string, mixed>|null */
    public function lock(int $id): ?array
    {
        $query = $this->query()->whereKey($id);
        $query->lockForUpdate();

        return $query->first()?->toArray();
    }

    /**
     * @param array<string, mixed> $changes */
    public function checkpoint(int $id, string $token, array $changes): void
    {
        foreach (['errors', 'result'] as $key) {
            if (isset($changes[$key])) {
                $changes[$key] = json_encode($changes[$key], JSON_THROW_ON_ERROR);
            }
        }
        $query = $this->query()->whereKey($id)->where('worker_token', $token)->where('status', 'running');
        $updated = $query->update($changes + ['updated_at' => time()]);
        if ($updated !== 1 && !$query->exists()) {
            throw new \core\queue\PermanentJobFailure('任务已取消或执行权已转移');
        }
    }

    public function fail(int $id, string $token): void
    {
        $this->query()->whereKey($id)->where('worker_token', $token)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'updated_at' => time()]);
    }

    /**
     * @return list<array<string, mixed>> */
    public function expired(int $before): array
    {
        return $this->query()->whereIn('status', ['completed', 'failed', 'cancelled'])->where('updated_at', '<', $before)->limit(1000)->get()->toArray();
    }
}
