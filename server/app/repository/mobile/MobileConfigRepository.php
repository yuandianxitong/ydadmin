<?php

declare(strict_types=1);

namespace app\repository\mobile;

use app\model\mobile\MobileConfig;
use core\base\Model;
use core\base\Repository;

/**
 * 移动端配置仓储（mobile_configs 表，全站一行）。
 *
 * 不受数据权限约束：表里没有 created_by / dept_id。
 * 不声明 $dataScoped，沿用基类默认值。
 */
class MobileConfigRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new MobileConfig();
    }

    /** @return array<string, mixed>|null */
    public function findSingleton(): ?array
    {
        $query = $this->query();
        $query->orderBy($this->qualify('id'));

        return $query->first()?->toArray();
    }

    /**
     * 有行则更新该 id，无行则插入（补 created_at）。JSON 列必须传数组，由模型 cast 编码。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsert(array $data): array
    {
        $row = $this->findSingleton();
        if ($row === null) {
            $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

            return $this->create($data);
        }
        $this->update((int) $row['id'], $data);

        return $this->findSingleton() ?? [];
    }
}
