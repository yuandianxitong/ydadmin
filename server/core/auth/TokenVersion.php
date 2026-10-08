<?php

declare(strict_types=1);

namespace core\auth;

use support\Db;
use support\Redis;

/**
 * token 版本号（spec §4.4；M5a spec §4.2 起带 scope 维度）。签发时写进 payload 的 ver，认证中间件逐请求比对；
 * 禁用、删除、重置/修改密码、admin:init 时自增，该身份已签发的 token 全部失效。
 *
 * 账号在库里时，版本号以 admins.token_version / users.token_version 为准，并且必须和改账号的那条
 * UPDATE 处在同一个数据库事务里。Redis 只是读缓存会在「库已经提交、缓存还写着旧版本」或
 * 「Redis 写入失败、库已经改完」时把刚吊销的 token 放回来。0 表示还没播种：第一次读取写成
 * [1_000_000, 2_000_000_000] 里的随机数，不带 ver 的 token 按 0 比对，对不上。
 *
 * 库里没有这一行时（单测里的假 id）仍走 Redis：`admin_token_ver:{id}` / `user_token_ver:{id}`。
 * 缺键时 SETNX 播种，丢键就换一个随机数，fail closed。两个 scope 互不影响。
 */
final class TokenVersion
{
    private const SEED_MIN = 1_000_000;

    private const SEED_MAX = 2_000_000_000;

    /** @param string $scope 'admin'（管理员，默认）| 'user'（C 端会员） */
    public static function current(int $id, string $scope = 'admin'): int
    {
        $stored = self::accountVersion($id, $scope);
        if ($stored === null) {
            return self::redisCurrent($id, $scope);
        }
        $table = self::table($scope);
        if ($table === null || $stored !== 0) {
            return $stored;
        }

        $seed = random_int(self::SEED_MIN, self::SEED_MAX);
        // 并发的第一次读取只有一个把 0 写成种子，其余读回同一个值
        Db::table($table)->where('id', $id)->where('token_version', 0)->update(['token_version' => $seed]);

        return (int) Db::table($table)->where('id', $id)->value('token_version');
    }

    /**
     * 账号在库里时自增这一行（调用方要放进改账号的同一个事务）。没有这一行时走 Redis。
     */
    public static function bump(int $id, string $scope = 'admin'): int
    {
        if (self::accountVersion($id, $scope) === null) {
            self::redisCurrent($id, $scope);

            return (int) Redis::incr(self::key($id, $scope));
        }

        self::current($id, $scope);
        $table = self::table($scope);
        if ($table === null) {
            return self::redisCurrent($id, $scope);
        }
        Db::table($table)->where('id', $id)->increment('token_version');

        return (int) Db::table($table)->where('id', $id)->value('token_version');
    }

    private static function redisCurrent(int $id, string $scope): int
    {
        $key = self::key($id, $scope);
        $value = Redis::get($key);
        if ($value === false || $value === null) {
            Redis::setNx($key, (string) random_int(self::SEED_MIN, self::SEED_MAX));
            $value = Redis::get($key);
        }

        return (int) $value;
    }

    /** 没有这一行时返回 null。0 是「还没播种」，不是没有账号。 */
    private static function accountVersion(int $id, string $scope): ?int
    {
        $table = self::table($scope);
        if ($table === null) {
            return null;
        }
        $value = Db::table($table)->where('id', $id)->value('token_version');

        return $value === null ? null : (int) $value;
    }

    private static function table(string $scope): ?string
    {
        return match ($scope) {
            'admin' => 'admins',
            'user' => 'users',
            default => null,
        };
    }

    private static function key(int $id, string $scope): string
    {
        return "{$scope}_token_ver:{$id}";
    }
}
