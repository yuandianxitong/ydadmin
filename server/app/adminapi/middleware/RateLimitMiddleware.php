<?php
declare(strict_types=1);

namespace app\adminapi\middleware;

use think\Request;
use think\Response;
use think\facade\Cache;
use core\http\Response as CoreResponse;

class RateLimitMiddleware
{
    /**
     * 每分钟最大请求次数
     */
    protected int $maxAttempts = 60;

    /**
     * 时间窗口（秒）
     */
    protected int $decaySeconds = 60;

    public function handle(Request $request, \Closure $next): Response
    {
        $key = $this->resolveRequestKey($request);
        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= $this->maxAttempts) {
            return CoreResponse::error(lang('messages.too_many_requests'), 429);
        }

        Cache::set($key, $attempts + 1, $this->decaySeconds);

        /** @var Response $response */
        $response = $next($request);

        $remaining = max(0, $this->maxAttempts - $attempts - 1);

        return $response->header([
            'X-RateLimit-Limit'     => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) $remaining,
        ]);
    }

    protected function resolveRequestKey(Request $request): string
    {
        $ip = $request->ip();
        $path = $request->pathinfo();
        return 'rate_limit:' . md5($ip . '|' . $path);
    }
}
