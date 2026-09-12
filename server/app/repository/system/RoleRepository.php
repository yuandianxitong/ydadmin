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

    /** 管理员是否持有系统角色（is_system=1）。不看管理员自身状态、也不看角色状态：防提权判定要 fail closed。 */
    public function adminHoldsSystemRole(int $adminId): bool
    {
        return $this->query()
            ->join('admin_roles', 'admin_roles.role_id', '=', $this->qualify('id'))
            ->where('admin_roles.admin_id', $adminId)
            ->where($this->qualify('is_system'), 1)
            ->exists();
    }

    /**
     * 管理员当前持有的角色 id（升序）。不含软删角色（与 AdminService::validRoleIds() 能接受的集合一致），含禁用角色。
     *
     * @return list<int>
     */
    public function getRoleIdsByAdminId(int $adminId): array
    {
        $ids = array_map('intval', $this->query()
            ->join('admin_roles', 'admin_roles.role_id', '=', $this->qualify('id'))
            ->where('admin_roles.admin_id', $adminId)
            ->pluck($this->qualify('id'))
            ->all());
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * 一组角色授予的菜单 id 并集（升序去重）。只服务于防提权判定，所以不看角色状态、禁用的角色照样算数：
     * 与 adminHoldsSystemRole() 同口径，防提权判定要 fail closed——否则非超管可以授出一个「菜单更宽但被禁用」
     * 的角色蒙混过关，等超管哪天启用它，被授权的人就静默提权了。已软删的角色不计（软删作用域），
     * 已软删的菜单也不计（AdminService::getAdminInfo() 的 menu_ids 同样不含）。
     * 生效权限（core\auth\Permission）与实际数据范围（DataScopeResolver::compute()）仍然只认启用的角色，不受影响。
     *
     * @param array<int, int> $roleIds
     * @return list<int>
     */
    public function getMenuIdsByRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }
        $ids = array_map('intval', $this->query()
            ->join('role_menus', 'role_menus.role_id', '=', $this->qualify('id'))
            ->join('menus', 'menus.id', '=', 'role_menus.menu_id')
            ->whereIn($this->qualify('id'), $roleIds)
            ->whereNull('menus.deleted_at')
            ->pluck('role_menus.menu_id')
            ->all());
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
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
