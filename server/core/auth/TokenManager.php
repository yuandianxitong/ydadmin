<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Cache;
use think\facade\Config;
use core\exception\AuthException;

class TokenManager
{
    protected string $key;
    protected string $algorithm;
    protected int $expire;
    protected string $issuer;

    public function __construct()
    {
        $this->key = Config::get('auth.jwt.key', '');
        if (empty($this->key)) {
            throw new \RuntimeException('JWT key 未配置，请在 config/auth.php 的 jwt.key 中设置密钥或配置 JWT_SECRET 环境变量');
        }
        if ($this->key === 'your-secret-key') {
            \think\facade\Log::warning('JWT 使用了默认密钥 your-secret-key，生产环境请务必更换');
        }
        $this->algorithm = Config::get('auth.jwt.algorithm', 'HS256');
        $this->expire = (int) Config::get('auth.jwt.expire', 7200);
        $this->issuer = Config::get('auth.jwt.issuer', 'yd-admin');
    }

    /**
     * 生成Token
     */
    public function generate(array $payload): string
    {
        $now = time();
        $data = [
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $this->expire,
            'data' => $payload
        ];

        return JWT::encode($data, $this->key, $this->algorithm);
    }

    /**
     * 验证Token
     */
    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, $this->algorithm));
            $payload = (array) $decoded;

            // 检查Token是否在黑名单中
            if ($this->isBlacklisted($token)) {
                throw new AuthException(lang('auth.token_expired'));
            }

            return (array) $payload['data'];
        } catch (\Exception $e) {
            throw new AuthException(lang('auth.token_invalid') . ': ' . $e->getMessage());
        }
    }

    /**
     * 刷新Token
     */
    public function refresh(string $token): string
    {
        $payload = $this->verify($token);
        $this->blacklist($token);
        return $this->generate($payload);
    }

    /**
     * 将Token加入黑名单
     */
    public function blacklist(string $token): bool
    {
        $key = 'blacklist_token:' . md5($token);
        return Cache::set($key, 1, $this->expire);
    }

    /**
     * 检查Token是否在黑名单中
     */
    public function isBlacklisted(string $token): bool
    {
        $key = 'blacklist_token:' . md5($token);
        return Cache::has($key);
    }

    /**
     * 从请求头获取Token
     */
    public function getTokenFromHeader(): ?string
    {
        $header = request()->header('Authorization', '');
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    /**
     * 解析Token获取用户ID
     */
    public function getUserId(string $token): int
    {
        $payload = $this->verify($token);
        return (int) ($payload['user_id'] ?? 0);
    }

    /**
     * 解析Token获取用户信息
     */
    public function getUserInfo(string $token): array
    {
        return $this->verify($token);
    }
}
