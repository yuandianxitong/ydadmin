<?php

declare(strict_types=1);

namespace core\auth;

use support\Db;

/** 权限和数据范围的持久化代次。bump 必须与授权变更处在同一个事务中。 */
final class AuthorizationVersion
{
    public static function current(): int
    {
        $version = Db::table('authorization_state')->where('id', 1)->value('version');
        if ($version === null) {
            throw new \RuntimeException('缺少授权版本，请执行 php webman yd:update');
        }

        return (int) $version;
    }

    public static function bump(): void
    {
        if (Db::connection()->transactionLevel() === 0) {
            throw new \LogicException('授权版本必须在授权变更事务内更新');
        }
        if (Db::table('authorization_state')->where('id', 1)->increment('version') !== 1) {
            throw new \RuntimeException('缺少授权版本，请执行 php webman yd:update');
        }
    }
}
