<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Role;
use core\base\Model;
use core\base\Repository;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use support\Db;

class RoleRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at'];

    protected function getModel(): Model
    {
        return new Role();
    }

    /**
     * 列表：每行含 admins_count、menus_count 与 dept_ids（自定义数据范围的部门）。
     *
     * @param array<int|string, mixed> $where
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getListWithStats(array $where, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->withCount(['admins', 'menus'])->where($where);
        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('sort'))->orderBy($this->qualify('id'), 'desc')
            ->forPage($page, $limit)->get()->toArray();

        $deptMap = $this->getDeptIdsByRoleIds(array_map('intval', array_column($list, 'id')));
        foreach ($list as &$row) {
            $row['dept_ids'] = $deptMap[(int) $row['id']] ?? [];
        }
        unset($row);

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /** @return array<string, mixed>|null */
    public function getDetailWithMenus(int $id): ?array
    {
        return $this->query()->with('menus')->where($this->qualify('id'), $id)->first()?->toArray();
    }

    /**
     * 全量覆盖角色的菜单授权。
     *
     * @param array<int, int> $menuIds
     */
    public function assignMenus(int $roleId, array $menuIds): void
    {
        $this->replacePivot('role_menus', 'menu_id', $roleId, $menuIds);
    }

    /**
     * 全量覆盖角色的自定义数据范围部门。
     *
     * @param array<int, int> $deptIds
     */
    public function assignDepartments(int $roleId, array $deptIds): void
    {
        $this->replacePivot('role_departments', 'department_id', $roleId, $deptIds);
    }

    /**
     * @param array<int, int> $roleIds
     * @return array<int, list<int>> roleId → 部门 id（升序）
     */
    public function getDeptIdsByRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }
        $map = [];
        foreach (Db::table('role_departments')->whereIn('role_id', $roleIds)->orderBy('department_id')->get(['role_id', 'department_id']) as $row) {
            $map[(int) $row->role_id][] = (int) $row->department_id;
        }

        return $map;
    }

    /**
     * 启用的角色选项（只含 id、name、title，契约 §2.2/§2.3）。
     *
     * @return list<array{id: int, name: string, title: string}>
     */
    public function getAllEnabled(): array
    {
        $query = $this->query()->where($this->qualify('status'), 1);
        $query->orderBy($this->qualify('sort'));
        $query->orderBy($this->qualify('id'));

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'name'  => (string) $row['name'],
            'title' => (string) $row['title'],
        ], $query->get(['id', 'name', 'title'])->toArray());
    }

    /** 角色标识是否已占用。含软删行（唯一索引对软删行同样生效）。 */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->query()->withoutGlobalScope(SoftDeletingScope::class)->where($this->qualify('name'), $name);
        if ($excludeId > 0) {
            $query->where($this->qualify('id'), '<>', $excludeId);
        }

        return $query->exists();
    }

    /** 角色下是否还有未删除的管理员（删除保护，spec §4.6）。 */
    public function isUsedByAdmin(int $roleId): bool
    {
        return Db::table('admin_roles as ar')
            ->join('admins as a', 'a.id', '=', 'ar.admin_id')
            ->where('ar.role_id', $roleId)
            ->whereNull('a.deleted_at')
            ->exists();
    }

    /**
     * 角色下全部管理员 id（清权限缓存用）。
     *
     * @return list<int>
     */
    public function getAdminIdsByRoleId(int $roleId): array
    {
        return array_values(array_map('intval', Db::table('admin_roles')->where('role_id', $roleId)->pluck('admin_id')->all()));
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

    /** @param array<int, int> $ids */
    public function containsSystemRole(array $ids): bool
    {
        return $ids !== [] && $this->query()->whereIn($this->qualify('id'), $ids)->where($this->qualify('is_system'), 1)->exists();
    }

    /** @param array<int, int> $ids */
    private function replacePivot(string $table, string $column, int $roleId, array $ids): void
    {
        $now = date('Y-m-d H:i:s');
        Db::table($table)->where('role_id', $roleId)->delete();
        $rows = array_map(static fn (int $id): array => [
            'role_id'    => $roleId,
            $column      => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values(array_unique(array_map('intval', $ids))));
        if ($rows !== []) {
            Db::table($table)->insert($rows);
        }
    }
}
