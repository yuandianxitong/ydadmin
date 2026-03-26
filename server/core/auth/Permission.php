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
use core\exception\PermissionException;

class Permission
{
    protected array $permissions = [];
    protected array $roles = [];

    /**
     * 检查用户权限
     */
    public function check(int $userId, string $permission): bool
    {
        // 超级管理员(ID=1)拥有所有权限
        if ($userId === 1) {
            return true;
        }

        $userPermissions = $this->getUserPermissions($userId);
        return in_array($permission, $userPermissions) || in_array('*', $userPermissions);
    }

    /**
     * 检查用户角色
     */
    public function hasRole(int $userId, string $role): bool
    {
        $userRoles = $this->getUserRoles($userId);
        return in_array($role, $userRoles) || in_array('super_admin', $userRoles);
    }

    /**
     * 获取用户权限列表
     */
    public function getUserPermissions(int $userId): array
    {
        $cacheKey = "user_permissions:{$userId}";

        return Cache::remember($cacheKey, function() use ($userId) {
            $permissions = [];

            // 通过角色获取权限
            $roles = $this->getUserRoles($userId);
            foreach ($roles as $role) {
                $rolePermissions = $this->getRolePermissions($role);
                $permissions = array_merge($permissions, $rolePermissions);
            }

            // 直接分配的权限
            $directPermissions = $this->getDirectPermissions($userId);
            $permissions = array_merge($permissions, $directPermissions);

            return array_unique($permissions);
        }, 3600);
    }

    /**
     * 获取用户角色列表
     */
    public function getUserRoles(int $userId): array
    {
        $cacheKey = "user_roles:{$userId}";

        return Cache::remember($cacheKey, function() use ($userId) {
            return db('admin_roles')
                ->alias('ar')
                ->join('roles r', 'ar.role_id = r.id')
                ->where('ar.admin_id', $userId)
                ->where('r.status', 1)
                ->column('r.name');
        }, 3600);
    }

    /**
     * 获取角色权限（从菜单按钮获取）
     */
    protected function getRolePermissions(string $role): array
    {
        $cacheKey = "role_permissions:{$role}";

        return Cache::remember($cacheKey, function() use ($role) {
            return db('role_menus')
                ->alias('rm')
                ->join('menus m', 'rm.menu_id = m.id')
                ->join('roles r', 'rm.role_id = r.id')
                ->where('r.name', $role)
                ->whereIn('m.type', [2, 3])
                ->where('m.status', 1)
                ->where('m.permission', '<>', '')
                ->column('m.permission');
        }, 3600);
    }

    /**
     * 获取直接权限
     */
    protected function getDirectPermissions(int $userId): array
    {
        return [];
    }

    /**
     * 清除用户权限缓存
     */
    public function clearUserCache(int $userId): void
    {
        Cache::delete("user_permissions:{$userId}");
        Cache::delete("user_roles:{$userId}");
    }

    /**
     * 清除角色权限缓存
     */
    public function clearRoleCache(string $role): void
    {
        Cache::delete("role_permissions:{$role}");
    }

    /**
     * 权限中间件检查
     */
    public function middleware(string $permission): \Closure
    {
        return function($request, \Closure $next) use ($permission) {
            $userId = $request->userId ?? 0;

            if (!$userId) {
                throw new \core\exception\AuthException(lang('auth.please_login'));
            }

            if (!$this->check($userId, $permission)) {
                throw new PermissionException(lang('auth.permission_denied'));
            }

            return $next($request);
        };
    }
}
