<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\FailedJob;
use core\base\Model;
use core\base\Repository;

/** 队列失败任务仓储（不受数据权限约束；表里没有创建人列）。硬删一律 query()->delete()。 */
class FailedJobRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new FailedJob();
    }

    /**
     * @param array{queue: string, payload: array<array-key, mixed>, exception: string, attempts: int, failed_at: string} $data
     * @return int 新行 id
     */
    public function record(array $data): int
    {
        return (int) $this->create($data)['id'];
    }

    /** @return list<array<string, mixed>> 新的在前 */
    public function latest(int $limit): array
    {
        return array_values($this->query()
            ->orderByDesc($this->qualify('id'))
            ->limit(max(1, $limit))
            ->get()
            ->toArray());
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->find($id);
    }

    public function deleteById(int $id): bool
    {
        return $this->query()->where($this->qualify('id'), $id)->delete() > 0;
    }

    /** @return list<int> 全部 id，升序（先失败的先重投） */
    public function ids(): array
    {
        return array_values(array_map(
            'intval',
            $this->query()->orderBy($this->qualify('id'))->pluck($this->qualify('id'))->all()
        ));
    }

    /** $before 为 null 时删除全部；否则删除 failed_at 早于它的行。 @return int 删除条数 */
    public function deleteOlderThan(?string $before): int
    {
        $query = $this->query();
        if ($before !== null) {
            $query->where($this->qualify('failed_at'), '<', $before);
        }

        return (int) $query->delete();
    }
}
