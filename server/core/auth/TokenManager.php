<?php

declare(strict_types=1);

namespace core\auth;

use core\exception\AuthException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use support\Cache;
use support\Log;
use Webman\Http\Request;

/**
 * JWT 签发与校验。admin / user 两个 scope 各自独立 secret + issuer，token 不能跨 scope 使用。
 *
 * 与 TP8 版保持的契约（admin 前端依赖）：claims 顶层含 iat/exp，前端在剩余有效期不足 50% 时
 * 静默刷新；exp - iat = 24 小时；刷新时自 login_at 起 7 天为绝对上限。
 * 黑名单以 jti 为 key 存 Redis，TTL = token 剩余有效期。
 */
final class TokenManager
{
    private const SCOPES = ['admin', 'user'];

    /** @var array<string, self> scope → 实例：由部署期配置构造，构造后只读（check:context 白名单） */
    private static array $instances = [];

    private function __construct(
        private readonly string $scope,
        private readonly string $key,
        private readonly string $issuer,
        private readonly string $algorithm,
        private readonly int $expire,
        private readonly int $refreshExpire,
    ) {
    }

    public static function scope(string $scope): self
    {
        if (!in_array($scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException("Unknown JWT scope: {$scope}");
        }

        return self::$instances[$scope] ??= self::build($scope);
    }

    /** 测试钩子：配置变更后丢弃实例缓存。 */
    public static function flushInstances(): void
    {
        self::$instances = [];
    }

    private static function build(string $scope): self
    {
        $config = (array) config('auth.jwt', []);
        $scopeConfig = (array) ($config[$scope] ?? []);
        $key = (string) ($scopeConfig['key'] ?? '');
        if ($key === '') {
            throw new \RuntimeException('JWT_' . strtoupper($scope) . '_SECRET 未配置');
        }
        if (strlen($key) < 32) {
            Log::warning(sprintf('JWT %s scope 密钥不足 32 字节（当前 %d 字节），生产环境务必更换', $scope, strlen($key)));
        }

        return new self(
            $scope,
            $key,
            (string) ($scopeConfig['issuer'] ?? "ydadmin-{$scope}"),
            (string) ($config['algorithm'] ?? 'HS256'),
            (int) ($config['expire'] ?? 86400),
            (int) ($config['refresh_expire'] ?? 604800),
        );
    }

    /** @param array<string, mixed> $payload */
    public function generate(array $payload, ?int $loginAt = null): string
    {
        $now = time();

        return JWT::encode([
            'iss'      => $this->issuer,
            'iat'      => $now,
            'exp'      => $now + $this->expire,
            'login_at' => $loginAt ?? $now,
            'jti'      => bin2hex(random_bytes(16)),
            'scope'    => $this->scope,
            'data'     => $payload,
        ], $this->key, $this->algorithm);
    }

    /**
     * @return array<string, mixed> 签发时的 payload
     * @throws AuthException 签名错误、已过期、scope/issuer 不符、已拉黑
     */
    public function verify(string $token): array
    {
        $claims = $this->decode($token);
        if ($this->isBlacklisted($claims)) {
            throw new AuthException(lang('auth.token_expired'));
        }

        return $this->payloadOf($claims);
    }

    /** 换发新 token 并拉黑旧 token；沿用 login_at，超过 7 天上限必须重新登录。 */
    public function refresh(string $token): string
    {
        $claims = $this->decode($token);
        if ($this->isBlacklisted($claims)) {
            throw new AuthException(lang('auth.token_expired'));
        }
        $loginAt = (int) ($claims['login_at'] ?? $claims['iat']);
        if (time() - $loginAt > $this->refreshExpire) {
            throw new AuthException(lang('auth.login_expired'));
        }
        $this->blacklistClaims($claims);

        return $this->generate($this->payloadOf($claims), $loginAt);
    }

    /** 拉黑 token（登出用）。无效 token 静默忽略——它本来就无法通过校验。 */
    public function blacklist(string $token): void
    {
        try {
            $claims = $this->decode($token);
        } catch (AuthException) {
            return;
        }
        $this->blacklistClaims($claims);
    }

    public function getTokenFromHeader(Request $request): ?string
    {
        $header = (string) $request->header('authorization', '');

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }

    /** @return array<string, mixed> */
    private function decode(string $token): array
    {
        try {
            $claims = (array) JWT::decode($token, new Key($this->key, $this->algorithm));
        } catch (\Throwable) {
            throw new AuthException(lang('auth.token_invalid'));
        }
        if (($claims['scope'] ?? null) !== $this->scope
            || ($claims['iss'] ?? null) !== $this->issuer
            || !isset($claims['jti'], $claims['exp'], $claims['data'])) {
            throw new AuthException(lang('auth.token_invalid'));
        }

        return $claims;
    }

    /**
     * @param array<string, mixed> $claims
     * @return array<string, mixed>
     */
    private function payloadOf(array $claims): array
    {
        return (array) json_decode((string) json_encode($claims['data']), true);
    }

    /** @param array<string, mixed> $claims */
    private function blacklistClaims(array $claims): void
    {
        Cache::set($this->blacklistKey((string) $claims['jti']), 1, max(1, (int) $claims['exp'] - time()));
    }

    /** @param array<string, mixed> $claims */
    private function isBlacklisted(array $claims): bool
    {
        return Cache::has($this->blacklistKey((string) $claims['jti']));
    }

    private function blacklistKey(string $jti): string
    {
        return "jwt_blacklist_{$this->scope}_{$jti}";
    }
}
