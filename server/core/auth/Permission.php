<?php

declare(strict_types=1);

namespace core\auth;

use core\permission\PermissionCheckerInterface;
use support\Db;
use support\Redis;

/**
 * RBAC（spec §4.2）。
 * - 超管：拥有任一 status=1、未删除、is_system=1 的角色。
 * - 权限集：admin_roles → roles(status=1, 未删除) → role_menus → menus(type∈{2,3}, status=1, 未删除, permission 非空)
 *   的 permission 去重；超管为 ['*', ...全部]，'*' 在首位（前端 hasPermission('*') 短路放行）。
 * - 缓存按管理员分 key 存 Redis；注册表（Redis SET）记录本类写过的 key，clearAllCache() 只删注册表里的，
 *   不能用 Cache::clear()——那会连同 token 黑名单一起清掉。
 * 容器单例，没有实例状态。
 */
final class Permission implements PermissionCheckerInterface
{
    private const TTL = 3600;

    private const REGISTRY = 'perm.registry';

    public function isSuperAdmin(int $adminId): bool
    {
        return (bool) $this->remember("perm.super.{$adminId}", static fn (): bool => Db::table('admin_roles as ar')
            ->join('roles as r', 'ar.role_id', '=', 'r.id')
            ->where('ar.admin_id', $adminId)
            ->where('r.is_system', 1)
            ->where('r.status', 1)
            ->whereNull('r.deleted_at')
            ->exists());
    }

    public function check(int $adminId, string $permission): bool
    {
        return $this->isSuperAdmin($adminId) || in_array($permission, $this->getUserPermissions($adminId), true);
    }

    /** @return list<string> */
    public function getUserPermissions(int $adminId): array
    {
        return array_values((array) $this->remember("perm.list.{$adminId}", function () use ($adminId): array {
            $menus = Db::table('menus')
                ->whereIn('type', [2, 3])
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->whereNotNull('permission')
                ->where('permission', '<>', '');
            if ($this->isSuperAdmin($adminId)) {
                return array_values(array_unique(['*', ...$menus->pluck('permission')->all()]));
            }
            $roleIds = Db::table('admin_roles as ar')
                ->join('roles as r', 'ar.role_id', '=', 'r.id')
                ->where('ar.admin_id', $adminId)
                ->where('r.status', 1)
                ->whereNull('r.deleted_at')
                ->pluck('r.id')
                ->all();
            if ($roleIds === []) {
                return [];
            }

            return array_values(array_unique($menus
                ->whereIn('id', static fn ($query) => $query->select('menu_id')->from('role_menus')->whereIn('role_id', $roleIds))
                ->pluck('permission')
                ->all()));
        }));
    }

    public function clearUserCache(int $adminId): void
    {
        Redis::del("perm.super.{$adminId}", "perm.list.{$adminId}");
    }

    /** 菜单/按钮变更会影响所有人的权限集时调用。 */
    public function clearAllCache(): void
    {
        $keys = (array) Redis::sMembers(self::REGISTRY);
        if ($keys !== []) {
            Redis::del(...$keys);
        }
        Redis::del(self::REGISTRY);
    }

    private function remember(string $key, \Closure $compute): mixed
    {
        $cached = Redis::get($key);
        if (is_string($cached)) {
            return json_decode($cached, true);
        }
        $value = $compute();
        // 先登记再写值：clearAllCache() 与写入并发时，最坏情况是注册表里多一个不存在的 key
        Redis::sAdd(self::REGISTRY, $key);
        Redis::setEx($key, self::TTL, (string) json_encode($value));

        return $value;
    }
}
