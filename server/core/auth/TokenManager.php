<?php

declare(strict_types=1);

namespace core\auth;

use core\exception\AuthException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use support\Cache;
use support\Redis;
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

    /**
     * php-jwt 7 对 HS256 短密钥直接抛异常，所以短密钥必须在构造时就失败并给出明确原因，
     * 而不是等到签发时报一个难以理解的错误。
     */
    public static function assertKeyStrength(string $scope, string $key): void
    {
        $name = 'JWT_' . strtoupper($scope) . '_SECRET';
        if ($key === '') {
            throw new \RuntimeException("{$name} 未配置");
        }
        if (strlen($key) < 32) {
            throw new \RuntimeException(sprintf('%s 不足 32 字节（当前 %d 字节），请用 php -r "echo bin2hex(random_bytes(32));" 重新生成', $name, strlen($key)));
        }
    }

    private static function build(string $scope): self
    {
        // 注意：不用 config() 的单参数点号写法直接连接 auth 与 jwt——那样拼出的
        // 字符串字面量恰好落进 tests\Unit\Support\LangKeysTest 扫描 lang 键的正则
        // （group.key 形状），会被误判成缺失的 lang 键。这里改成两段取值，语义不变。
        $config = (array) (config('auth', [])['jwt'] ?? []);
        $scopeConfig = (array) ($config[$scope] ?? []);
        $key = (string) ($scopeConfig['key'] ?? '');
        self::assertKeyStrength($scope, $key);

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
        return $this->verifyClaims($token)['payload'];
    }

    /**
     * 与 verify() 同样的校验，另外带出 jti、exp 与会话的绝对到期时间（M4：WS 票据要记下签发它的 token 的
     * jti 与会话到期时间，复查时据此判断连接是否已被登出 / 已超过 7 天登录上限）。
     *
     * session_expires_at = login_at + refresh_expire（刷新也越不过的绝对上限）；没有 login_at 时退回 exp。
     *
     * @return array{payload: array<string, mixed>, jti: string, exp: int, session_expires_at: int}
     * @throws AuthException 签名错误、已过期、scope/issuer 不符、已拉黑
     */
    public function verifyClaims(string $token): array
    {
        $claims = $this->decode($token);
        if ($this->isBlacklisted($claims)) {
            throw new AuthException(lang('auth.token_expired'));
        }

        return [
            'payload'            => $this->payloadOf($claims),
            'jti'                => (string) $claims['jti'],
            'exp'                => (int) $claims['exp'],
            'session_expires_at' => isset($claims['login_at']) ? (int) $claims['login_at'] + $this->refreshExpire : (int) $claims['exp'],
        ];
    }

    /** jti 是否已被拉黑（M4：WS 进程定期复查已建立的连接用，连接本身不再持有 token）。 */
    public function isJtiRevoked(string $jti): bool
    {
        return Cache::has($this->blacklistKey($jti));
    }

    /**
     * 吊销 token 所属的整个登录会话（登出用）。会话 id 是登录时写进 payload 的 sid，刷新原样沿用，
     * 所以「刷新」只拉黑旧 jti、不影响会话，「登出」才让同一会话的 WS 连接断开。
     * TTL = refresh_expire：会话自 login_at 起最长就这么久，之后 WS 连接也会按绝对到期时间关闭。
     * 不带 sid 的 token 与无效 token 静默忽略。
     */
    public function revokeSession(string $token): void
    {
        try {
            $sid = $this->payloadOf($this->decode($token))['sid'] ?? null;
        } catch (AuthException) {
            return;
        }
        if (is_string($sid) && $sid !== '') {
            Cache::set($this->sessionRevokedKey($sid), 1, max(1, $this->refreshExpire));
        }
    }

    /** 会话是否已被登出吊销（M4：WS 进程判定用）。空 sid 永远返回 false。 */
    public function isSessionRevoked(string $sid): bool
    {
        return $sid !== '' && Cache::has($this->sessionRevokedKey($sid));
    }

    /**
     * 换发新 token 并拉黑旧 token；沿用 login_at，超过 7 天上限必须重新登录。
     * 同一个 token 被并发刷新时只允许一个请求成功（SET NX 抢锁，TTL = 旧 token 剩余有效期）。
     *
     * $payloadOverrides 里的 'ver' 同时兼作「调用方读到的最新版本号」：必须与旧 token 自带的
     * ver 一致才允许刷新，否则说明在「校验旧 token」与「签发新 token」这两次读版本号之间，
     * 该管理员已被禁用/删除/改密码等操作把版本号 bump 过了——此时必须让旧 token 直接失效，
     * 不能把新版本号原样盖到刚刷新出的新 token 上（那样等于让一次已被吊销的刷新继续生效）。
     *
     * @param array<string, mixed> $payloadOverrides 覆盖旧 payload 的字段（如当前 token 版本号 ver）
     */
    public function refresh(string $token, array $payloadOverrides = []): string
    {
        $claims = $this->decode($token);
        if ($this->isBlacklisted($claims)) {
            throw new AuthException(lang('auth.token_expired'));
        }
        $loginAt = (int) ($claims['login_at'] ?? $claims['iat']);
        if (time() - $loginAt > $this->refreshExpire) {
            throw new AuthException(lang('auth.login_expired'));
        }
        $ttl = max(1, (int) $claims['exp'] - time());
        if (Redis::set("refresh_lock.{$this->scope}.{$claims['jti']}", '1', 'EX', $ttl, 'NX') !== true) {
            throw new AuthException(lang('auth.token_expired'));
        }
        $payload = $this->payloadOf($claims);
        if (array_key_exists('ver', $payloadOverrides) && (int) ($payload['ver'] ?? 0) !== (int) $payloadOverrides['ver']) {
            throw new AuthException(lang('auth.token_expired'));
        }
        $this->blacklistClaims($claims);

        return $this->generate(array_merge($payload, $payloadOverrides), $loginAt);
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
        return $this->isJtiRevoked((string) $claims['jti']);
    }

    private function blacklistKey(string $jti): string
    {
        return "jwt_blacklist_{$this->scope}_{$jti}";
    }

    private function sessionRevokedKey(string $sid): string
    {
        return "jwt_session_revoked_{$this->scope}_{$sid}";
    }
}
