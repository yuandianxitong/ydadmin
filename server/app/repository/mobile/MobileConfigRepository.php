<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\mobile;

use app\model\mobile\MobileConfig;
use core\base\Repository;
use think\Model;

class MobileConfigRepository extends Repository
{
    protected function getModel(): Model
    {
        return new MobileConfig();
    }

    public function findSingleton(): ?array
    {
        $row = $this->model->order('id', 'asc')->find();
        return $row ? $row->toArray() : null;
    }

    /** @param array<string,mixed> $data */
    public function upsert(array $data): array
    {
        $row = $this->findSingleton();
        $now = date('Y-m-d H:i:s');
        if ($row === null) {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            return $this->create($data);
        }
        $data['updated_at'] = $now;
        $this->update((int) $row['id'], $data);
        return $this->findSingleton() ?? [];
    }
}
