<?php

declare(strict_types=1);

namespace core\datascope;

use core\context\RequestContext;
use support\Container;
use support\Context;

/**
 * 数据权限的请求级入口（spec §5.1）。当前管理员只取 RequestContext::actingUser()（与权限中间件一致）；
 * 为 0 时不过滤（CLI、队列、C 端）。快照在本请求第一次查受控表时解析并放入 Context（懒解析）。
 * 状态只放 Context，没有静态可变属性。
 */
final class DataScope
{
    public const ALL = 1;
    public const DEPT = 2;
    public const DEPT_AND_CHILDREN = 3;
    public const SELF = 4;
    public const CUSTOM = 5;

    private const K_SNAPSHOT = 'ctx.datascope.snapshot';
    private const K_BYPASS = 'ctx.datascope.bypass';

    public static function current(): ?DataScopeSnapshot
    {
        if ((int) (Context::get(self::K_BYPASS) ?? 0) > 0) {
            return null;
        }
        $adminId = RequestContext::actingUser();
        if ($adminId <= 0) {
            return null;
        }
        $snapshot = Context::get(self::K_SNAPSHOT);
        if ($snapshot instanceof DataScopeSnapshot && $snapshot->adminId === $adminId) {
            return $snapshot;
        }
        $snapshot = Container::get(DataScopeResolver::class)->resolve($adminId);
        Context::set(self::K_SNAPSHOT, $snapshot);

        return $snapshot;
    }

    /**
     * 在闭包内关闭数据权限（查重、认证查找、查看自己的资料等必须看全表的场景）。支持嵌套。
     *
     * @template T
     * @param \Closure(): T $fn
     * @return T
     */
    public static function bypass(\Closure $fn): mixed
    {
        Context::set(self::K_BYPASS, (int) (Context::get(self::K_BYPASS) ?? 0) + 1);
        try {
            return $fn();
        } finally {
            Context::set(self::K_BYPASS, (int) Context::get(self::K_BYPASS) - 1);
        }
    }
}
