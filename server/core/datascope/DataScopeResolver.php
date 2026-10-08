<?php

declare(strict_types=1);

namespace core\datascope;

use support\Db;
use support\Redis;

/**
 * 把管理员全部启用角色合并为 DataScopeSnapshot（spec §5.1）：
 * 超管或任一角色为「全部」→ all；2 取本部门；3 取本部门及全部下级；5 取 role_departments；任一「仅本人」→ self。
 * 结果缓存在 Redis，key 带上读取前的代次。角色、角色部门、部门树、管理员所属部门变更时只把代次加一，
 * 计算途中写回的旧结果落在旧 key 上，之后的请求读不到。
 * simulate() 用同一套合并规则预演「给定部门 + 给定角色」的范围，不读管理员当前的部门与角色、不读写缓存
 * （M1b 防提权：授权前先算出目标改完之后能看到什么）。
 * 容器单例，没有实例状态。
 */
final class DataScopeResolver
{
    private const TTL = 3600;

    public function resolve(int $adminId): DataScopeSnapshot
    {
        $epoch = self::counter('datascope.epoch');
        $userEpoch = self::counter('datascope.user.' . $adminId);
        $key = 'datascope.' . $adminId . '.' . $epoch . '.' . $userEpoch;
        $cached = Redis::get($key);
        if (is_string($cached)) {
            return DataScopeSnapshot::fromArray((array) json_decode($cached, true));
        }
        $snapshot = $this->compute($adminId);
        Redis::setEx($key, self::TTL, (string) json_encode($snapshot->toArray()));

        return $snapshot;
    }

    /**
     * 预演：按给定部门与角色计算数据范围，不缓存。$adminId 只写进快照（「仅本人」按它过滤）；新建管理员时传 0。
     * 默认与 compute() 同口径——只算启用、未删除的角色，系统角色或「全部」→ all。
     *
     * $countDisabledRoles 为真时把禁用的角色也算上，只给防提权判定用（fail closed）：否则非超管可以授出
     * 一个「范围更大但被禁用」的角色蒙混过关，等超管哪天启用它，被授权的人就静默拿到了更大的数据范围。
     * 软删的角色任何时候都不算。
     *
     * @param array<int, int> $roleIds
     */
    public function simulate(int $adminId, ?int $departmentId, array $roleIds, bool $countDisabledRoles = false): DataScopeSnapshot
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $roles = [];
        if ($roleIds !== []) {
            $query = Db::table('roles')->whereIn('id', $roleIds)->whereNull('deleted_at');
            if (!$countDisabledRoles) {
                $query->where('status', 1);
            }
            $roles = $query->get(['id', 'data_scope', 'is_system'])->all();
        }

        return $this->merge($adminId, $departmentId, $roles);
    }

    public function forget(int $adminId): void
    {
        Redis::incr('datascope.user.' . $adminId);
    }

    /** 角色/部门变更会影响所有人的数据范围时调用。只推进代次。 */
    public function forgetAll(): void
    {
        Redis::incr('datascope.epoch');
    }

    private static function counter(string $key): int
    {
        $value = Redis::get($key);

        return is_numeric($value) ? (int) $value : 0;
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
     * @param array<int, \stdClass> $roles 每行含 id、data_scope、is_system；调用方已筛掉软删的角色（是否含禁用角色由调用方决定）
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
