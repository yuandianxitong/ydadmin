<?php

declare(strict_types=1);

namespace app\repository\operations;

use app\model\operations\QueueOutbox;
use core\base\Model;
use core\base\Repository;

final class OutboxRepository extends Repository
{
    protected function getModel(): Model
    {
        return new QueueOutbox();
    }

    /**
     * @param array<string, mixed> $row */
    public function insertDeduplicated(array $row): int
    {
        if ($row['dedup_key'] === null) {
            return (int) $this->create($row)['id'];
        }
        $serialized = $row;
        $serialized['payload'] = json_encode($row['payload'], JSON_THROW_ON_ERROR);
        $this->query()->insertOrIgnore($serialized);
        $existing = $this->query()->where('dedup_key', $row['dedup_key'])->firstOrFail()->toArray();
        if ($existing['queue'] !== $row['queue'] || \core\support\PayloadFingerprint::hash($existing['payload']) !== \core\support\PayloadFingerprint::hash($row['payload'])) {
            throw new \InvalidArgumentException('投递去重键已用于不同任务');
        }

        return (int) $existing['id'];
    }

    /**
     * @return list<int> */
    public function due(int $limit): array
    {
        return $this->query()->whereIn('status', [0, 1, 2])->where('available_at', '<=', time())
            ->where('lease_until', '<=', time())->orderBy('id')->limit($limit)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    /**
     * @return array<string, mixed>|null */
    public function leaseForPublish(int $id, string $token): ?array
    {
        $updated = $this->query()->whereKey($id)->whereIn('status', [0, 1, 2])
            ->where('available_at', '<=', time())->where('lease_until', '<=', time())
            ->update(['status' => 1, 'lease_token' => $token, 'lease_until' => time() + 30, 'updated_at' => time()]);

        return $updated ? $this->find($id) : null;
    }

    public function published(int $id, string $token): void
    {
        $this->query()->whereKey($id)->where('status', 1)->where('lease_token', $token)
            ->update(['lease_until' => 0, 'available_at' => time() + 60, 'updated_at' => time()]);
    }

    /**
     * @return array<string, mixed>|null */
    public function claim(int $id, string $queue, string $token): ?array
    {
        $query = $this->query()->whereKey($id)->where('queue', $queue);
        $query->lockForUpdate();
        $row = $query->first()?->toArray();
        if ($row === null || (int) $row['status'] >= 3
            || ((int) $row['status'] === 2 && (int) $row['lease_until'] > time())
            || ((int) $row['status'] === 0 && (int) $row['available_at'] > time())) {
            return null;
        }
        $this->update($id, [
            'status' => 2, 'attempts' => (int) $row['attempts'] + 1, 'lease_token' => $token,
            'lease_until' => time() + (int) config('operations.delivery_lease_seconds', 3600), 'updated_at' => time(),
        ]);

        return $this->find($id);
    }

    /**
     * @param array<string, mixed> $changes */
    public function ownedUpdate(int $id, string $token, array $changes): bool
    {
        $query = $this->query()->whereKey($id)->where('status', 2)->where('lease_token', $token);

        return $query->update($changes + ['updated_at' => time()]) > 0 || $query->exists();
    }

    /**
     * @return array<string, int> */
    public function statistics(): array
    {
        return [
            'pending' => $this->query()->whereIn('status', [0, 1])->count(),
            'running' => $this->query()->where('status', 2)->count(),
            'failed' => $this->query()->where('status', 4)->count(),
            'oldest_pending_seconds' => max(0, time() - (int) ($this->query()->whereIn('status', [0, 1])->min('created_at') ?? time())),
        ];
    }

    public function purge(int $before): int
    {
        return $this->query()->whereIn('status', [3, 4])->where('updated_at', '<', $before)->limit(1000)->delete();
    }

    public function hasActiveLease(string $queue): bool
    {
        return $this->query()->where('queue', $queue)->where('status', 2)->where('lease_until', '>', time())->exists();
    }
}
