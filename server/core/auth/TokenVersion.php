<?php

declare(strict_types=1);

namespace core\auth;

use support\Redis;

/**
 * token 版本号（spec §4.4；M5a spec §4.2 起带 scope 维度）。签发时写进 payload 的 ver，认证中间件逐请求比对；
 * 禁用、删除、重置/修改密码、admin:init 时自增，该身份已签发的 token 全部失效。只存 Redis，不查库。
 *
 * scope 是**可选**第二参数，默认 'admin'：key 仍是 `admin_token_ver:{id}`，M1/M4 的调用点
 * （AdminService、ForceLogoutService、AdminAuthMiddleware、WebSocketServer）一行不改、行为不变；
 * C 端用 `user_token_ver:{id}`。两个 scope 的版本号互不影响——禁用一个会员不会把同 id 的管理员踢下线。
 *
 * 随机基数：key 不存在时先用 SETNX 写入 [1_000_000, 2_000_000_000] 内的一个随机数再读回，版本号不再是 0。
 * Redis 丢键（淘汰、FLUSHDB、未持久化就重启）后会重新播种出另一个随机数，所有旧 token 的 ver 都对不上：
 * fail closed，全员重新登录，被吊销的 token 不会复活。不带 ver 的 token 按 0 比对，一律失效。
 */
final class TokenVersion
{
    private const SEED_MIN = 1_000_000;

    private const SEED_MAX = 2_000_000_000;

    /** @param string $scope 'admin'（管理员，默认）| 'user'（C 端会员） */
    public static function current(int $id, string $scope = 'admin'): int
    {
        $key = self::key($id, $scope);
        $value = Redis::get($key);
        if ($value === false || $value === null) {
            // SETNX：并发的首批请求只有一个写入成功，其余读回同一个值
            Redis::setNx($key, (string) random_int(self::SEED_MIN, self::SEED_MAX));
            $value = Redis::get($key);
        }

        return (int) $value;
    }

    /** 先确保已播种再 INCR：对不存在的 key 直接 INCR 得到 1，可能恰好等于某个旧 token 的 ver。 */
    public static function bump(int $id, string $scope = 'admin'): int
    {
        self::current($id, $scope);

        return (int) Redis::incr(self::key($id, $scope));
    }

    private static function key(int $id, string $scope): string
    {
        return "{$scope}_token_ver:{$id}";
    }
}
