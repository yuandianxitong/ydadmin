<?php

declare(strict_types=1);

namespace core\auth;

use support\Db;

/** Redis 黑名单的持久化依据，jti 和 sid 均按 scope 隔离。 */
final class TokenRevocation
{
    public static function record(string $scope, string $kind, string $identifier, int $expiresAt): void
    {
        Db::table('auth_revocations')->upsert([
            'scope' => $scope,
            'kind' => $kind,
            'identifier' => $identifier,
            'expires_at' => $expiresAt,
        ], ['scope', 'kind', 'identifier'], ['expires_at']);

        // 吊销写入时顺带有界清理；未过期记录始终保留。
        Db::table('auth_revocations')->where('expires_at', '<=', time())->limit(100)->delete();
    }

    public static function contains(string $scope, string $kind, string $identifier): bool
    {
        return Db::table('auth_revocations')
            ->where('scope', $scope)
            ->where('kind', $kind)
            ->where('identifier', $identifier)
            ->where('expires_at', '>', time())
            ->exists();
    }
}
