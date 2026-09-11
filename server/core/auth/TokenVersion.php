<?php

declare(strict_types=1);

namespace core\auth;

use support\Redis;

/**
 * 管理员 token 版本号（spec §4.4）。签发时写进 payload 的 ver，AdminAuthMiddleware 逐请求比对；
 * 禁用、删除、重置/修改密码、admin:init 时自增，该管理员已签发的 token 全部失效。只存 Redis，不查库。
 */
final class TokenVersion
{
    public static function current(int $adminId): int
    {
        return (int) (Redis::get(self::key($adminId)) ?: 0);
    }

    public static function bump(int $adminId): int
    {
        return (int) Redis::incr(self::key($adminId));
    }

    private static function key(int $adminId): string
    {
        return "admin_token_ver:{$adminId}";
    }
}
