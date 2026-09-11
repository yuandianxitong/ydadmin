<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\DepartmentRepository;
use app\repository\system\MenuRepository;
use app\repository\system\RoleRepository;
use core\auth\Permission;
use core\base\Service;
use core\context\RequestContext;
use core\datascope\DataScope;
use core\datascope\DataScopeResolver;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 角色（契约 §2.3）。角色的授权、状态、data_scope、dept_ids 变更后，经 afterCommit 清掉该角色下
 * 每个管理员的权限缓存与数据范围缓存（下一请求即生效）；角色变更不自增 token 版本号（spec §4.4）。
 */
class RoleService extends Service
{
    #[Inject]
    protected RoleRepository $roleRepository;

    #[Inject]
    protected MenuRepository $menuRepository;

    #[Inject]
    protected DepartmentRepository $departmentRepository;

    #[Inject]
    protected Permission $permission;

    #[Inject]
    protected DataScopeResolver $dataScopeResolver;

    /**
     * @param array<string, mixed> $params keyword（name/title 模糊）、status
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getRoleList(array $params, int $page, int $limit): array
    {
        $where = [];
        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $where[] = [static function ($query) use ($like): void {
                $query->where('roles.name', 'like', $like)->orWhere('roles.title', 'like', $like);
            }];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['roles.status', '=', (int) $params['status']];
        }

        return $this->roleRepository->getListWithStats($where, $page, $limit);
    }

    /** @return list<array{id: int, name: string, title: string}> */
    public function getAllRoleOptions(): array
    {
        return $this->roleRepository->getAllEnabled();
    }

    /**
     * 角色授权（契约：show 与 {id}/permissions 都返回它）。
     *
     * @return array{menu_ids: list<int>, menus: array<int, array<string, mixed>>}
     */
    public function getRolePermissions(int $id): array
    {
        $role = $this->roleRepository->getDetailWithMenus($id);
        if ($role === null) {
            return ['menu_ids' => [], 'menus' => []];
        }

        return ['menu_ids' => array_map('intval', array_column($role['menus'], 'id')), 'menus' => $role['menus']];
    }

    /**
     * 新建角色（is_system 恒为 0：系统角色只来自种子）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createRole(array $data): array
    {
        if ($this->roleRepository->existsName((string) $data['name'])) {
            throw new BusinessException(lang('business.role_code_exists'));
        }
        $menuIds = $this->validMenuIds((array) ($data['menu_ids'] ?? []));
        $dataScope = (int) ($data['data_scope'] ?? DataScope::ALL);
        $deptIds = $dataScope === DataScope::CUSTOM ? $this->validDeptIds((array) ($data['dept_ids'] ?? [])) : [];
        $row = [
            'name'        => $data['name'],
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'data_scope'  => $dataScope,
            'is_system'   => 0,
            'status'      => (int) ($data['status'] ?? 1),
            'sort'        => (int) ($data['sort'] ?? 0),
            'created_by'  => RequestContext::actingUser() ?: null,
        ];

        return $this->runInTransaction(function () use ($row, $menuIds, $deptIds): array {
            $role = $this->roleRepository->create($row);
            $this->roleRepository->assignMenus((int) $role['id'], $menuIds);
            $this->roleRepository->assignDepartments((int) $role['id'], $deptIds);

            return $role + ['dept_ids' => $deptIds];
        });
    }

    /** @param array<string, mixed> $data 字段均可选；不含 menu_ids（授权走 assignPermissions） */
    public function updateRole(int $id, array $data): void
    {
        $role = $this->findOrFail($id);
        if (!empty($role['is_system'])) {
            if (isset($data['name']) && $data['name'] !== $role['name']) {
                throw new BusinessException(lang('business.system_role_no_modify'));
            }
            if (isset($data['status']) && (int) $data['status'] !== (int) $role['status']) {
                throw new BusinessException(lang('business.system_role_no_status'));
            }
        }
        if (isset($data['name']) && $this->roleRepository->existsName((string) $data['name'], $id)) {
            throw new BusinessException(lang('business.role_code_exists'));
        }

        $update = array_filter(
            array_intersect_key($data, array_flip(['name', 'title', 'description', 'data_scope', 'status', 'sort'])),
            static fn ($value) => $value !== null
        );
        $update['updated_by'] = RequestContext::actingUser() ?: null;
        // 非自定义范围一律清空部门；自定义范围只在提交了 dept_ids 时覆盖
        $dataScope = (int) ($data['data_scope'] ?? $role['data_scope']);
        $deptIds = null;
        if ($dataScope !== DataScope::CUSTOM) {
            $deptIds = [];
        } elseif (array_key_exists('dept_ids', $data)) {
            $deptIds = $this->validDeptIds((array) ($data['dept_ids'] ?? []));
        }

        $this->runInTransaction(function () use ($id, $update, $deptIds): void {
            $this->roleRepository->update($id, $update);
            if ($deptIds !== null) {
                $this->roleRepository->assignDepartments($id, $deptIds);
            }
            $this->afterCommit(fn () => $this->forgetRoleMembers($id));
        });
    }

    public function deleteRole(int $id): void
    {
        $role = $this->findOrFail($id);
        if (!empty($role['is_system'])) {
            throw new BusinessException(lang('business.system_role_no_delete'));
        }
        if ($this->roleRepository->isUsedByAdmin($id)) {
            throw new BusinessException(lang('business.role_has_admins'));
        }
        $this->roleRepository->delete($id);
    }

    /**
     * 任一失败整体回滚。
     *
     * @param array<int, int|string> $ids
     */
    public function batchDeleteRoles(array $ids): void
    {
        $this->runInTransaction(function () use ($ids): void {
            foreach ($ids as $id) {
                $this->deleteRole((int) $id);
            }
        });
    }

    /**
     * 全量覆盖授权（只认 menu_ids）。系统角色不可改：code 403。
     *
     * @param array<int, mixed> $menuIds
     */
    public function assignPermissions(int $id, array $menuIds): void
    {
        $role = $this->findOrFail($id);
        if (!empty($role['is_system'])) {
            throw new BusinessException(lang('business.system_role_no_permission'), 403);
        }
        $menuIds = $this->validMenuIds($menuIds);

        $this->runInTransaction(function () use ($id, $menuIds): void {
            $this->roleRepository->assignMenus($id, $menuIds);
            $this->afterCommit(fn () => $this->forgetRoleMembers($id));
        });
    }

    public function updateStatus(int $id, int $status): void
    {
        $role = $this->findOrFail($id);
        if (!empty($role['is_system'])) {
            throw new BusinessException(lang('business.system_role_no_status'));
        }

        $this->runInTransaction(function () use ($id, $status): void {
            $this->roleRepository->update($id, ['status' => $status, 'updated_by' => RequestContext::actingUser() ?: null]);
            $this->afterCommit(fn () => $this->forgetRoleMembers($id));
        });
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->roleRepository->find($id) ?? throw new BusinessException(lang('business.role_not_found'));
    }

    private function forgetRoleMembers(int $roleId): void
    {
        foreach ($this->roleRepository->getAdminIdsByRoleId($roleId) as $adminId) {
            $this->permission->clearUserCache($adminId);
            $this->dataScopeResolver->forget($adminId);
        }
    }

    /**
     * @param array<int, mixed> $menuIds
     * @return list<int>
     */
    private function validMenuIds(array $menuIds): array
    {
        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
        if (count($this->menuRepository->existingIds($menuIds)) !== count($menuIds)) {
            throw new BusinessException(lang('business.menu_not_found'));
        }

        return $menuIds;
    }

    /**
     * @param array<int, mixed> $deptIds
     * @return list<int>
     */
    private function validDeptIds(array $deptIds): array
    {
        $deptIds = array_values(array_unique(array_map('intval', $deptIds)));
        if (count($this->departmentRepository->existingIds($deptIds)) !== count($deptIds)) {
            throw new BusinessException(lang('business.dept_not_found'));
        }

        return $deptIds;
    }
}
