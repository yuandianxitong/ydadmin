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
        $key = 'api_rate:' . $request->ip() . ':' . $request->pathinfo();

        if (!$this->checkRateLimit($key, 60, 60)) {
            return $this->errorResponse(lang('messages.too_many_requests'), 429);
        }

        return $next($request);
    }
}
