<?php

declare(strict_types=1);

namespace app\repository\operations;

use app\model\operations\HttpIdempotency;
use core\base\Model;
use core\base\Repository;

final class IdempotencyRepository extends Repository
{
    protected function getModel(): Model
    {
        return new HttpIdempotency();
    }

    /**
     * @param array<string, mixed> $identity
     * @return array<string, mixed> */
    public function reserve(array $identity, string $fingerprint): array
    {
        // ON DUPLICATE KEY UPDATE 直接获取排他锁，避免多个回放请求从共享锁升级时互相死锁。
        $this->query()->upsert(
            [$identity + ['fingerprint' => $fingerprint, 'expires_at' => time() + 86400]],
            ['actor_id', 'operation', 'key_hash'],
            ['key_hash']
        );

        $query = $this->query()->where($identity);
        $query->lockForUpdate();

        return $query->firstOrFail()->toArray();
    }

    public function purge(): int
    {
        return $this->query()->where('expires_at', '<', time())->limit(1000)->delete();
    }
}
