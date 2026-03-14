<?php
declare(strict_types=1);

namespace app\api\middleware;

use core\http\Middleware;
use Closure;
use think\Request;
use think\Response;

class ApiRateLimitMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->pathinfo();

        // 登录/注册接口：同一 IP 每分钟 10 次
        if ($this->isAuthPath($path)) {
            $key = 'api_rate:auth:' . $request->ip();
            if (!$this->checkRateLimit($key, 10, 60)) {
                return $this->errorResponse(lang('messages.too_many_requests'), 429);
            }
        }

        // 通用接口：同一用户每分钟 60 次（已认证用户）
        $userId = $request->userId ?? null;
        if ($userId) {
            $key = 'api_rate:user:' . $userId . ':' . $path;
            if (!$this->checkRateLimit($key, 60, 60)) {
                return $this->errorResponse(lang('messages.too_many_requests'), 429);
            }
        } else {
            // 未认证 IP 级别限流
            $key = 'api_rate:ip:' . $request->ip() . ':' . $path;
            if (!$this->checkRateLimit($key, 60, 60)) {
                return $this->errorResponse(lang('messages.too_many_requests'), 429);
            }
        }

        return $next($request);
    }

    protected function isAuthPath(string $path): bool
    {
        return str_contains($path, 'auth/login')
            || str_contains($path, 'auth/register')
            || str_contains($path, 'auth/sms-login');
    }
}
