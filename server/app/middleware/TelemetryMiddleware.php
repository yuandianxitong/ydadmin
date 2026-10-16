<?php

declare(strict_types=1);

namespace app\middleware;

use support\Log;
use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class TelemetryMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $start = hrtime(true);
        $code = 500;
        try {
            $response = $handler($request);
            $body = json_decode((string) $response->rawBody(), true);
            $code = is_array($body) ? (int) ($body['code'] ?? $response->getStatusCode()) : $response->getStatusCode();

            return $response;
        } catch (\Throwable $e) {
            $code = $e instanceof \core\exception\BusinessException ? (int) $e->getCode() : 500;
            throw $e;
        } finally {
            $milliseconds = (hrtime(true) - $start) / 1000000;
            $label = $request->method() . ':' . ($request->controller ?: 'unknown') . ':' . ($request->action ?: 'unknown');
            try {
                if ($milliseconds >= (int) config('operations.slow_request_ms', 1000)) {
                    Log::warning('http.slow', ['operation' => $label, 'duration_ms' => round($milliseconds, 2), 'code' => $code]);
                }
                $key = 'ops:http:' . date('YmdHi');
                Redis::hIncrBy($key, $label . ':count', 1);
                Redis::hIncrBy($key, $label . ':duration_us', (int) ($milliseconds * 1000));
                if ($code >= 400) {
                    Redis::hIncrBy($key, $label . ':errors', 1);
                }
                Redis::expire($key, 3600);
            } catch (\Throwable) {
                // 遥测故障不影响业务响应。
            }
        }
    }
}
