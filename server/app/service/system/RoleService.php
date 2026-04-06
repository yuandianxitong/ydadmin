<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\RoleRepository;
use app\repository\system\MenuRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\auth\Permission;
use think\facade\Db;

class RoleService extends Service
{
    protected RoleRepository $roleRepository;
    protected MenuRepository $menuRepository;
    protected Permission $permission;

    /**
     * 获取角色列表
     */
    public function getRoleList(array $params): array
    {
        $where = [];

        // 搜索条件
        if (!empty($params['keyword'])) {
            $where[] = ['name|title', 'like', '%' . $params['keyword'] . '%'];
        }

        if (isset($params['status'])) {
            $where[] = ['status', '=', $params['status']];
        }

        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 15);

        return $this->roleRepository->getListWithStats($where, $page, $limit);
    }

    /**
     * 获取所有角色选项
     */
    public function getAllRoleOptions(): array
    {
        return $this->roleRepository->getAllEnabled();
    }

    /**
     * 创建角色
     */
    public function createRole(array $data): array
    {
        // 验证角色名唯一性
        if ($this->roleRepository->existsName($data['name'])) {
            throw new BusinessException(lang('business.role_code_exists'));
        }

        Db::startTrans();
        try {
            // 创建角色
            $roleData = [
                'name' => $data['name'],
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'data_scope' => $data['data_scope'] ?? 1,
                'is_system' => 0,
                'status' => $data['status'] ?? 1,
                'sort' => $data['sort'] ?? 0,
                'created_by' => $data['created_by'] ?? 0,
            ];

            $role = $this->roleRepository->create($roleData);

            // 分配菜单权限
            if (!empty($data['menu_ids'])) {
                $this->roleRepository->assignMenus($role['id'], $data['menu_ids']);
            }

            Db::commit();

            $this->log('创建角色成功', ['role_id' => $role['id']]);

            return $role;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 更新角色
     */
    public function updateRole(int $id, array $data): bool
    {
        $role = $this->roleRepository->find($id);
        if (!$role) {
            throw new BusinessException(lang('business.role_not_found'));
        }

        // 系统角色不能修改标识
        if ($role['is_system'] && isset($data['name']) && $data['name'] !== $role['name']) {
            throw new BusinessException(lang('business.system_role_no_modify'));
        }

        // 验证角色名唯一性
        if (isset($data['name']) && $this->roleRepository->existsName($data['name'], $id)) {
            throw new BusinessException(lang('business.role_code_exists'));
        }

        Db::startTrans();
        try {
            // 更新基本信息
            $updateData = array_filter([
                'name' => $data['name'] ?? null,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'data_scope' => $data['data_scope'] ?? null,
                'status' => $data['status'] ?? null,
                'sort' => $data['sort'] ?? null,
                'updated_by' => $data['updated_by'] ?? 0,
            ], function($value) {
                return $value !== null;
            });

            $result = $this->roleRepository->update($id, $updateData);

            // 更新菜单权限
            if (isset($data['menu_ids'])) {
                $this->roleRepository->assignMenus($id, $data['menu_ids']);
            }

            Db::commit();

            $this->clearPermissionCache($id, $role['name']);
            $this->log('更新角色成功', ['role_id' => $id]);

            return $result;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 删除角色
     */
    public function deleteRole(int $id): bool
    {
        $role = $this->roleRepository->find($id);
        if (!$role) {
            throw new BusinessException(lang('business.role_not_found'));
        }

        // 系统角色不能删除
        if ($role['is_system']) {
            throw new BusinessException(lang('business.system_role_no_delete'));
        }

        // 检查是否有管理员使用此角色
        if ($this->roleRepository->isUsedByAdmin($id)) {
            throw new BusinessException(lang('business.role_has_admins'));
        }

        $result = $this->roleRepository->delete($id);

        if ($result) {
            $this->log('删除角色成功', ['role_id' => $id]);
        }

        return $result;
    }

    /**
     * 批量删除角色
     *
     * 使用事务包裹：任一删除失败则整体回滚。
     */
    public function batchDeleteRole(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }
        return $this->runInTransaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->deleteRole((int) $id);
            }
            return true;
        });
    }

    /**
     * 获取角色权限
     */
    public function getRolePermissions(int $id): array
    {
        $role = $this->roleRepository->getDetailWithMenus($id);
        if (!$role) {
            return [
                'menu_ids' => [],
                'menus' => []
            ];
        }

        $menuIds = array_column($role['menus'], 'id');

        return [
            'menu_ids' => $menuIds,
            'menus' => $role['menus']
        ];
    }

    /**
     * 分配菜单权限
     */
    public function assignPermissions(int $id, array $menuIds = []): bool
    {
        $role = $this->roleRepository->find($id);
        if (!$role) {
            throw new BusinessException(lang('business.role_not_found'));
        }

        Db::startTrans();
        try {
            $this->roleRepository->assignMenus($id, $menuIds);

            Db::commit();

            $this->clearPermissionCache($id, $role['name']);
            $this->log('角色授权成功', ['role_id' => $id]);

            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 清除角色相关的权限缓存
     */
    protected function clearPermissionCache(int $roleId, ?string $roleName = null): void
    {
        // 清除角色权限缓存
        if ($roleName) {
            $this->permission->clearRoleCache($roleName);
        }

        // 清除该角色下所有用户的权限缓存
        $adminIds = $this->roleRepository->getAdminIdsByRoleId($roleId);
        foreach ($adminIds as $adminId) {
            $this->permission->clearUserCache($adminId);
        }
    }

    /**
     * 更新角色状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $role = $this->roleRepository->find($id);
        if (!$role) {
            throw new BusinessException(lang('business.role_not_found'));
        }

        if ($role['is_system'] == 1) {
            throw new BusinessException(lang('business.system_role_no_status'));
        }

        $result = $this->roleRepository->update($id, ['status' => $status]);

        $this->clearPermissionCache($id, $role['name']);
        $this->log('更新角色状态', ['role_id' => $id, 'status' => $status]);

        return $result;
    }
}
