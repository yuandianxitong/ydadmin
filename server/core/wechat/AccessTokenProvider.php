<?php

declare(strict_types=1);

namespace core\wechat;

use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatUnavailableException;
use support\Redis;

/**
 * 微信服务端 access_token（M6a spec §3.2）。
 *
 * 存 Redis 而不是进程内：webman 多个 worker 进程共享同一份 token；微信的 cgi-bin/token 有每日调用上限，
 * 而且每刷新一次，旧 token 在短暂过渡期后失效——多个 worker 各刷各的会互相把对方的 token 顶掉。
 *
 * 未命中时 SET NX 抢锁：
 *   - 抢到：再读一次缓存（前一个持锁者可能刚写完），仍无则请求并写缓存，TTL = max(expires_in − 300, 60)；
 *     finally 用 Lua「值相等才删」释放锁，只删自己的锁（锁可能已过期被别人重新拿到）。
 *   - 没抢到：每 200ms 读一次缓存，至多 token_wait_ms，仍无 → WechatUnavailableException。
 * 持锁者崩溃时锁 token_lock_seconds 后自动过期，下一次调用重新抢。
 *
 * 容器单例、无状态（锁值是局部变量）。
 */
final class AccessTokenProvider
{
    private const CACHE_PREFIX = 'wechat:access_token:';

    private const LOCK_PREFIX = 'wechat:access_token_lock:';

    private const RELEASE_LOCK = "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) end return 0";

    private const POLL_INTERVAL_US = 200_000;

    /** access_token 提前多少秒视为过期，给在途请求留余量 */
    private const EXPIRY_MARGIN = 300;

    private const MIN_TTL = 60;

    public function __construct(private readonly WechatHttpClient $http)
    {
    }

    /** @throws WechatApiException|WechatUnavailableException */
    public function token(WechatAppConfig $config): string
    {
        $cached = $this->cached($config->appId);
        if ($cached !== null) {
            return $cached;
        }

        $lockKey = self::LOCK_PREFIX . $config->appId;
        $lockValue = bin2hex(random_bytes(16));
        $lockSeconds = max(1, (int) config('wechat.token_lock_seconds', 10));

        if (Redis::set($lockKey, $lockValue, 'EX', $lockSeconds, 'NX') === true) {
            try {
                return $this->cached($config->appId) ?? $this->fetch($config);
            } finally {
                Redis::eval(self::RELEASE_LOCK, 1, $lockKey, $lockValue);
            }
        }

        return $this->waitForOtherWorker($config->appId);
    }

    public function invalidate(string $appId): void
    {
        Redis::del(self::CACHE_PREFIX . $appId);
    }

    private function fetch(WechatAppConfig $config): string
    {
        $data = $this->http->get('cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid'      => $config->appId,
            'secret'     => $config->secret,
        ]);

        $token = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;
        if (!is_string($token) || $token === '' || !is_int($expiresIn)) {
            throw new WechatUnavailableException('微信接口 cgi-bin/token 应答缺少 access_token 或 expires_in');
        }

        Redis::set(self::CACHE_PREFIX . $config->appId, $token, 'EX', max($expiresIn - self::EXPIRY_MARGIN, self::MIN_TTL));

        return $token;
    }

    private function waitForOtherWorker(string $appId): string
    {
        $waitMs = max(0, (int) config('wechat.token_wait_ms', 3000));
        $deadline = hrtime(true) + $waitMs * 1_000_000;

        while (true) {
            $remainingUs = intdiv($deadline - hrtime(true), 1000);
            if ($remainingUs <= 0) {
                break;
            }
            usleep(min(self::POLL_INTERVAL_US, $remainingUs));
            $cached = $this->cached($appId);
            if ($cached !== null) {
                return $cached;
            }
        }

        throw new WechatUnavailableException('等待其他进程刷新微信 access_token 超时');
    }

    private function cached(string $appId): ?string
    {
        $value = Redis::get(self::CACHE_PREFIX . $appId);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
