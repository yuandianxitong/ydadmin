<?php

declare(strict_types=1);

namespace core\auth;

use support\Redis;

/**
 * 管理员 token 版本号（spec §4.4）。签发时写进 payload 的 ver，AdminAuthMiddleware 逐请求比对；
 * 禁用、删除、重置/修改密码、admin:init 时自增，该管理员已签发的 token 全部失效。只存 Redis，不查库。
 *
 * 随机基数：key 不存在时先用 SETNX 写入 [1_000_000, 2_000_000_000] 内的一个随机数再读回，版本号不再是 0。
 * Redis 丢键（淘汰、FLUSHDB、未持久化就重启）后会重新播种出另一个随机数，所有旧 token 的 ver 都对不上：
 * fail closed，全员重新登录，被吊销的 token 不会复活。不带 ver 的 token 按 0 比对，一律失效。
 */
final class TokenVersion
{
    private const SEED_MIN = 1_000_000;

    private const SEED_MAX = 2_000_000_000;

    public static function current(int $adminId): int
    {
        $key = self::key($adminId);
        $value = Redis::get($key);
        if ($value === false || $value === null) {
            // SETNX：并发的首批请求只有一个写入成功，其余读回同一个值
            Redis::setNx($key, (string) random_int(self::SEED_MIN, self::SEED_MAX));
            $value = Redis::get($key);
        }

        return (int) $value;
    }

    /** 先确保已播种再 INCR：对不存在的 key 直接 INCR 得到 1，可能恰好等于某个旧 token 的 ver。 */
    public static function bump(int $adminId): int
    {
        self::current($adminId);

        return (int) Redis::incr(self::key($adminId));
    }

    private static function key(int $adminId): string
    {
        return "admin_token_ver:{$adminId}";
    }
}
