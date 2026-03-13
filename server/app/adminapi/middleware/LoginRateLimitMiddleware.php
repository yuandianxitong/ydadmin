<?php
declare(strict_types=1);

namespace app\adminapi\middleware;

use think\Request;
use think\Response;
use think\facade\Cache;
use core\http\Response as CoreResponse;

class LoginRateLimitMiddleware
{
    /**
     * 每分钟最大登录尝试次数
     */
    protected int $maxAttempts = 5;

    /**
     * 锁定时间（秒）
     */
    protected int $lockoutSeconds = 300;

    public function handle(Request $request, \Closure $next): Response
    {
        $ip = $request->ip();
        $key = 'login_attempts:' . $ip;
        $lockKey = 'login_locked:' . $ip;

        // 检查是否被锁定
        if (Cache::get($lockKey)) {
            $ttl = Cache::get($lockKey . ':ttl', 0);
            $remaining = max(0, $ttl - time());
            return CoreResponse::error(sprintf(lang('messages.login_rate_limit'), $remaining), 429);
        }

        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= $this->maxAttempts) {
            // 锁定
            Cache::set($lockKey, true, $this->lockoutSeconds);
            Cache::set($lockKey . ':ttl', time() + $this->lockoutSeconds, $this->lockoutSeconds);
            Cache::delete($key);
            return CoreResponse::error(sprintf(lang('messages.login_rate_limit'), $this->lockoutSeconds), 429);
        }

        /** @var Response $response */
        $response = $next($request);

        // 如果登录成功（状态码200），重置计数
        $statusCode = $response->getCode();
        if ($statusCode === 200) {
            Cache::delete($key);
        } else {
            Cache::set($key, $attempts + 1, 60);
        }

        return $response;
    }
}
