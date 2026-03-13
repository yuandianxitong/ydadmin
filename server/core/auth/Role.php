<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\auth;

use think\facade\Cache;
use think\facade\Db;

class Role
{
    /**
     * 创建角色
     */
    public function create(string $name, string $title, string $description = ''): int
    {
        return Db::table('roles')->insertGetId([
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 分配权限给角色
     */
    public function assignPermissions(int $roleId, array $permissionIds): bool
    {
        Db::startTrans();
        try {
            // 删除原有权限
            Db::table('role_permissions')->where('role_id', $roleId)->delete();

            // 添加新权限
            $data = [];
            foreach ($permissionIds as $permissionId) {
                $data[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            if (!empty($data)) {
                Db::table('role_permissions')->insertAll($data);
            }

            Db::commit();

            // 清除相关缓存
            $roleName = $this->getRoleName($roleId);
            if ($roleName) {
                Cache::delete("role_permissions:{$roleName}");
            }

            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }

    /**
     * 分配角色给用户
     */
    public function assignToUser(int $userId, array $roleIds): bool
    {
        Db::startTrans();
        try {
            // 删除原有角色
            Db::table('user_roles')->where('user_id', $userId)->delete();

            // 添加新角色
            $data = [];
            foreach ($roleIds as $roleId) {
                $data[] = [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            if (!empty($data)) {
                Db::table('user_roles')->insertAll($data);
            }

            Db::commit();

            // 清除用户权限缓存
            $permission = new Permission();
            $permission->clearUserCache($userId);

            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }

    /**
     * 获取角色名称
     */
    protected function getRoleName(int $roleId): ?string
    {
        return Db::table('roles')->where('id', $roleId)->value('name');
    }

    /**
     * 获取所有角色
     */
    public function getAll(): array
    {
        return Db::table('roles')->where('status', 1)->order('id desc')->select()->toArray();
    }

    /**
     * 获取角色权限
     */
    public function getPermissions(int $roleId): array
    {
        return Db::table('role_permissions')
            ->alias('rp')
            ->join('permissions p', 'rp.permission_id = p.id')
            ->where('rp.role_id', $roleId)
            ->where('p.status', 1)
            ->field('p.*')
            ->select()
            ->toArray();
    }
}
