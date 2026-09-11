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
 * - 两处角色查询都要求管理员本人 status=1、未删除（fail-closed）：token 吊销依赖 Redis 里的版本号与黑名单，
 *   万一 Redis 丢了数据，已禁用/删除账号的旧 token 还能通过校验，但在这里拿不到任何权限，也不算超管。
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
            ->join('admins as a', 'a.id', '=', 'ar.admin_id')
            ->where('ar.admin_id', $adminId)
            ->where('a.status', 1)
            ->whereNull('a.deleted_at')
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
                ->join('admins as a', 'a.id', '=', 'ar.admin_id')
                ->where('ar.admin_id', $adminId)
                ->where('a.status', 1)
                ->whereNull('a.deleted_at')
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

    /**
     * 菜单/按钮变更会影响所有人的权限集时调用。
     * 注册表只增不删（见 remember()），所以这里能覆盖到当前所有存活的 key；
     * 唯一的残留是已经过期的 key 的名字留在集合里，无害——DEL 一个不存在的 key 是空操作。
     */
    public function clearAllCache(): void
    {
        $keys = (array) Redis::sMembers(self::REGISTRY);
        if ($keys !== []) {
            Redis::del(...$keys);
        }
    }

    private function remember(string $key, \Closure $compute): mixed
    {
        $cached = Redis::get($key);
        if (is_string($cached)) {
            return json_decode($cached, true);
        }
        $value = $compute();
        // 先登记再写值，且注册表永不整体清空：clearAllCache() 与本方法并发时，
        // 不会出现「registry 已清、这个 key 还没登记」的窗口，clearAllCache() 总能覆盖到它。
        Redis::sAdd(self::REGISTRY, $key);
        Redis::setEx($key, self::TTL, (string) json_encode($value));

        return $value;
    }
}
