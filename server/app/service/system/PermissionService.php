<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\PermissionRepository;
use core\base\Service;
use core\exception\BusinessException;

class PermissionService extends Service
{
    protected PermissionRepository $permissionRepository;

    /**
     * 获取权限列表
     */
    public function getPermissionList(array $params): array
    {
        $where = [];

        // 搜索条件
        if (!empty($params['keyword'])) {
            $where[] = ['name|title', 'like', '%' . $params['keyword'] . '%'];
        }

        if (!empty($params['group'])) {
            $where[] = ['group', '=', $params['group']];
        }

        if (isset($params['status'])) {
            $where[] = ['status', '=', $params['status']];
        }

        // 返回分组列表或普通列表
        if (!empty($params['grouped'])) {
            return $this->permissionRepository->getGroupedList($where);
        }

        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 15);

        return $this->permissionRepository->getList($where, $page, $limit);
    }

    /**
     * 获取权限树
     */
    public function getPermissionTree(): array
    {
        return $this->permissionRepository->getPermissionTree();
    }

    /**
     * 创建权限
     */
    public function createPermission(array $data): array
    {
        // 验证权限名唯一性
        if ($this->permissionRepository->existsName($data['name'])) {
            throw new BusinessException(lang('business.permission_code_exists'));
        }

        $permissionData = [
            'name' => $data['name'],
            'title' => $data['title'],
            'group' => $data['group'],
            'description' => $data['description'] ?? '',
            'guard_name' => $data['guard_name'] ?? 'admin',
            'status' => $data['status'] ?? 1,
            'sort' => $data['sort'] ?? 0,
        ];

        $permission = $this->permissionRepository->create($permissionData);

        $this->log('创建权限成功', ['permission_id' => $permission['id']]);

        return $permission;
    }

    /**
     * 更新权限
     */
    public function updatePermission(int $id, array $data): bool
    {
        $permission = $this->permissionRepository->find($id);
        if (!$permission) {
            throw new BusinessException(lang('business.permission_not_found'));
        }

        // 验证权限名唯一性
        if (isset($data['name']) && $this->permissionRepository->existsName($data['name'], $id)) {
            throw new BusinessException(lang('business.permission_code_exists'));
        }

        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'title' => $data['title'] ?? null,
            'group' => $data['group'] ?? null,
            'description' => $data['description'] ?? null,
            'guard_name' => $data['guard_name'] ?? null,
            'status' => $data['status'] ?? null,
            'sort' => $data['sort'] ?? null,
        ], function($value) {
            return $value !== null;
        });

        $result = $this->permissionRepository->update($id, $updateData);

        if ($result) {
            $this->log('更新权限成功', ['permission_id' => $id]);
        }

        return $result;
    }

    /**
     * 删除权限
     */
    public function deletePermission(int $id): bool
    {
        $permission = $this->permissionRepository->find($id);
        if (!$permission) {
            throw new BusinessException(lang('business.permission_not_found'));
        }

        // 检查是否有角色使用此权限
        if ($this->permissionRepository->isUsedByRole($id)) {
            throw new BusinessException(lang('business.permission_used_by_role'));
        }

        $result = $this->permissionRepository->delete($id);

        if ($result) {
            $this->log('删除权限成功', ['permission_id' => $id]);
        }

        return $result;
    }

    /**
     * 批量创建权限
     */
    public function batchCreatePermissions(array $permissions): bool
    {
        $createData = [];

        foreach ($permissions as $permission) {
            // 检查权限名是否存在
            if ($this->permissionRepository->existsName($permission['name'])) {
                continue;
            }

            $createData[] = [
                'name' => $permission['name'],
                'title' => $permission['title'],
                'group' => $permission['group'],
                'description' => $permission['description'] ?? '',
                'guard_name' => $permission['guard_name'] ?? 'admin',
                'status' => $permission['status'] ?? 1,
                'sort' => $permission['sort'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (empty($createData)) {
            throw new BusinessException(lang('business.no_permission_to_create'));
        }

        $result = $this->permissionRepository->batchCreate($createData);

        if ($result) {
            $this->log('批量创建权限成功', ['count' => count($createData)]);
        }

        return $result;
    }
}
