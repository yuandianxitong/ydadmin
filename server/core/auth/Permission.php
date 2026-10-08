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
 * - 两处角色查询都要求管理员本人 status=1、未删除（fail-closed）：已禁用/删除的账号在缓存未命中时拿不到任何权限。
 * - 缓存 key 带上读取前看到的代次（perm.epoch 与 perm.user.{id}）。失效只把代次加一。
 *   已经开始算、算完又写回的请求写进旧代次，后来的请求读新代次，看不到那份旧结果。
 *   不能用 Cache::clear()——那会连同 token 黑名单一起清掉。
 * 容器单例，没有实例状态。
 */
final class Permission implements PermissionCheckerInterface
{
    private const TTL = 3600;

    public function isSuperAdmin(int $adminId): bool
    {
        return (bool) $this->remember("perm.super.{$adminId}", $adminId, static fn (): bool => Db::table('admin_roles as ar')
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
        return array_values((array) $this->remember("perm.list.{$adminId}", $adminId, function () use ($adminId): array {
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
        Redis::incr('perm.user.' . $adminId);
    }

    /** 菜单/按钮变更会影响所有人的权限集时调用。只推进代次，不删别的 key。 */
    public function clearAllCache(): void
    {
        Redis::incr('perm.epoch');
    }

    private function remember(string $base, int $adminId, \Closure $compute): mixed
    {
        $epoch = self::counter('perm.epoch');
        $userEpoch = self::counter('perm.user.' . $adminId);
        $key = $base . '.' . $epoch . '.' . $userEpoch;
        $cached = Redis::get($key);
        if (is_string($cached)) {
            return json_decode($cached, true);
        }
        $value = $compute();
        // 代次是进来之前读的。失效发生在计算期间的话，这里写的是旧 key，下一次读取用的是新代次。
        Redis::setEx($key, self::TTL, (string) json_encode($value));

        return $value;
    }

    private static function counter(string $key): int
    {
        $value = Redis::get($key);

        return is_numeric($value) ? (int) $value : 0;
    }
}
