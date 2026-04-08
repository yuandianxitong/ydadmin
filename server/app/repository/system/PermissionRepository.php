<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Permission;
use core\base\Repository;
use think\Model;

class PermissionRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Permission();
    }

    /**
     * 获取权限列表（按分组）
     */
    public function getGroupedList(array $where = []): array
    {
        $permissions = $this->model->where($where)
            ->order('group asc, sort asc, id asc')
            ->select()
            ->toArray();

        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission['group']][] = $permission;
        }

        return $grouped;
    }

    /**
     * 获取权限树（用于前端树形选择）
     */
    public function getPermissionTree(): array
    {
        $permissions = $this->model->where('status', 1)
            ->order('group asc, sort asc, id asc')
            ->select()
            ->toArray();

        $tree = [];
        foreach ($permissions as $permission) {
            if (!isset($tree[$permission['group']])) {
                $tree[$permission['group']] = [
                    'label'    => $permission['group'],
                    'children' => [],
                ];
            }

            $tree[$permission['group']]['children'][] = [
                'id'    => $permission['id'],
                'label' => $permission['title'],
                'value' => $permission['name'],
            ];
        }

        return array_values($tree);
    }

    /**
     * 获取所有启用的权限
     */
    public function getAllEnabled(): array
    {
        return $this->model->where('status', 1)
            ->order('group asc, sort asc, id asc')
            ->field('id, name, title, group')
            ->select()
            ->toArray();
    }

    /**
     * 根据分组获取权限
     */
    public function getByGroup(string $group): array
    {
        return $this->model->where('group', $group)
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 检查权限名是否存在
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->model->where('name', $name);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 批量创建权限
     */
    public function batchCreate(array $permissions): bool
    {
        return $this->model->saveAll($permissions) !== false;
    }

    /**
     * 检查权限是否被角色使用
     */
    public function isUsedByRole(int $permissionId): bool
    {
        return \think\facade\Db::name('role_permissions')->where('permission_id', $permissionId)->count() > 0;
    }
}
