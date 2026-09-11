<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Department;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * 部门仓储。
 *
 * `getTree()`/`getAllEnabled()` 均返回扁平数组，树形组装交给 DepartmentService。
 */
class DepartmentRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort'];

    protected function getModel(): Model
    {
        return new Department();
    }

    /**
     * 获取部门扁平列表（供 Service 层构建树），支持 keyword（name/code 模糊）/status 过滤。
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getTree(array $params = []): array
    {
        $query = $this->query();

        if (!empty($params['keyword'])) {
            $keyword = Like::contains((string) $params['keyword']);
            $query->where(static function ($q) use ($keyword) {
                $q->where('name', 'like', $keyword)->orWhere('code', 'like', $keyword);
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get()->toArray();
    }

    /**
     * 已启用部门扁平列表（供选项树用，仅取渲染必需字段）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllEnabled(): array
    {
        return $this->query()->where('status', 1)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc')
            ->get(['id', 'parent_id', 'name', 'code'])
            ->toArray();
    }

    /**
     * 获取指定部门的全部子部门 ID（全量递归内存遍历，非 SQL 递归 CTE）。部门数据量通常
     * 远小于 region（几十到几百条），O(n²) 最坏情形也不影响性能。
     *
     * @return array<int, int>
     */
    public function getChildIds(int $parentId): array
    {
        $all = $this->query()->get(['id', 'parent_id'])->toArray();

        return $this->findChildIds($all, $parentId);
    }

    /**
     * @param array<int, array<string, mixed>> $list
     * @return array<int, int>
     */
    private function findChildIds(array $list, int $parentId): array
    {
        $ids = [];
        foreach ($list as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $childId = (int) $item['id'];
                $ids[] = $childId;
                $ids = array_merge($ids, $this->findChildIds($list, $childId));
            }
        }

        return $ids;
    }

    /** 部门编码是否已被占用。含软删行：唯一索引对软删行同样生效。 */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $query = $this->query()->withoutGlobalScope(SoftDeletingScope::class)->where('code', $code)->where('code', '<>', '');
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * @param array<int, int|string> $ids
     * @return list<int>
     */
    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(array_map('intval', $this->query()->whereIn($this->qualify('id'), $ids)->pluck($this->qualify('id'))->all()));
    }
}
