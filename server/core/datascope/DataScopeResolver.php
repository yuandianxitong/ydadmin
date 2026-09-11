<?php

declare(strict_types=1);

namespace core\datascope;

use support\Db;
use support\Redis;

/**
 * 把管理员全部启用角色合并为 DataScopeSnapshot（spec §5.1）：
 * 超管或任一角色为「全部」→ all；2 取本部门；3 取本部门及全部下级；5 取 role_departments；任一「仅本人」→ self。
 * 结果缓存在 Redis，角色、角色部门、部门树、管理员所属部门变更时由业务经 afterCommit 清除。
 * simulate() 用同一套合并规则预演「给定部门 + 给定角色」的范围，不读管理员当前的部门与角色、不读写缓存
 * （M1b 防提权：授权前先算出目标改完之后能看到什么）。
 * 容器单例，没有实例状态。
 */
final class DataScopeResolver
{
    private const TTL = 3600;

    private const REGISTRY = 'datascope.registry';

    public function resolve(int $adminId): DataScopeSnapshot
    {
        $key = self::key($adminId);
        $cached = Redis::get($key);
        if (is_string($cached)) {
            return DataScopeSnapshot::fromArray((array) json_decode($cached, true));
        }
        $snapshot = $this->compute($adminId);
        // 先登记再写值，且注册表永不整体清空：forgetAll() 与本方法并发时，
        // 不会出现「registry 已清、这个 key 还没登记」的窗口，forgetAll() 总能覆盖到它。
        Redis::sAdd(self::REGISTRY, $key);
        Redis::setEx($key, self::TTL, (string) json_encode($snapshot->toArray()));

        return $snapshot;
    }

    /**
     * 预演：按给定部门与角色计算数据范围，不缓存。与 compute() 同口径——只算启用、未删除的角色，
     * 系统角色或「全部」→ all。$adminId 只写进快照（「仅本人」按它过滤）；新建管理员时传 0。
     *
     * @param array<int, int> $roleIds
     */
    public function simulate(int $adminId, ?int $departmentId, array $roleIds): DataScopeSnapshot
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $roles = $roleIds === [] ? [] : Db::table('roles')
            ->whereIn('id', $roleIds)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'data_scope', 'is_system'])
            ->all();

        return $this->merge($adminId, $departmentId, $roles);
    }

    public function forget(int $adminId): void
    {
        Redis::del(self::key($adminId));
    }

    /**
     * 角色/部门变更会影响所有人的数据范围时调用。
     * 注册表只增不删（见 resolve()），所以这里能覆盖到当前所有存活的 key；
     * 唯一的残留是已经过期的 key 的名字留在集合里，无害——DEL 一个不存在的 key 是空操作。
     */
    public function forgetAll(): void
    {
        $keys = (array) Redis::sMembers(self::REGISTRY);
        if ($keys !== []) {
            Redis::del(...$keys);
        }
    }

    private static function key(int $adminId): string
    {
        return "datascope.{$adminId}";
    }

    private function compute(int $adminId): DataScopeSnapshot
    {
        $department = Db::table('admins')->where('id', $adminId)->value('department_id');
        $roles = Db::table('admin_roles as ar')
            ->join('roles as r', 'ar.role_id', '=', 'r.id')
            ->where('ar.admin_id', $adminId)
            ->where('r.status', 1)
            ->whereNull('r.deleted_at')
            ->get(['r.id', 'r.data_scope', 'r.is_system'])
            ->all();

        return $this->merge($adminId, $department === null ? null : (int) $department, $roles);
    }

    /**
     * compute() 与 simulate() 共用的合并规则。
     *
     * @param array<int, \stdClass> $roles 每行含 id、data_scope、is_system；调用方已只取启用、未删除的角色
     */
    private function merge(int $adminId, ?int $departmentId, array $roles): DataScopeSnapshot
    {
        $deptIds = [];
        $self = false;
        foreach ($roles as $role) {
            $scope = (int) $role->data_scope;
            if ((int) $role->is_system === 1 || $scope === DataScope::ALL) {
                return new DataScopeSnapshot(true, [], false, $adminId);
            }
            switch ($scope) {
                case DataScope::DEPT:
                    if ($departmentId !== null) {
                        $deptIds[] = $departmentId;
                    }
                    break;
                case DataScope::DEPT_AND_CHILDREN:
                    if ($departmentId !== null) {
                        $deptIds = [...$deptIds, $departmentId, ...$this->descendantIds($departmentId)];
                    }
                    break;
                case DataScope::SELF:
                    $self = true;
                    break;
                case DataScope::CUSTOM:
                    $custom = Db::table('role_departments')->where('role_id', $role->id)->pluck('department_id')->all();
                    $deptIds = [...$deptIds, ...array_map('intval', $custom)];
                    break;
            }
        }
        $deptIds = array_values(array_unique($deptIds));
        sort($deptIds);

        return new DataScopeSnapshot(false, $deptIds, $self, $adminId);
    }

    /** @return list<int> 全部下级部门（不含自身；防环） */
    private function descendantIds(int $departmentId): array
    {
        $children = [];
        foreach (Db::table('departments')->whereNull('deleted_at')->get(['id', 'parent_id']) as $row) {
            $children[(int) $row->parent_id][] = (int) $row->id;
        }
        $result = [];
        $queue = [$departmentId];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($children[$current] ?? [] as $child) {
                if ($child !== $departmentId && !in_array($child, $result, true)) {
                    $result[] = $child;
                    $queue[] = $child;
                }
            }
        }

        return $result;
    }
}
