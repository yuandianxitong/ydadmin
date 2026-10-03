<?php

declare(strict_types=1);

namespace app\middleware;

use support\Response;
use Webman\Http\Request;
use Webman\Http\Response as HttpResponse;
use Webman\MiddlewareInterface;

/**
 * CORS（spec §4.5）：按来源白名单放行并允许携带凭据。是全局中间件（config/middleware.php），fallback 也经过它，
 * 所以 OPTIONS 预检在这里直接返回（命中 204，否则 403），不进入业务。
 */
class CorsMiddleware implements MiddlewareInterface
{
    private const ALLOW_HEADERS = 'Authorization, Content-Type, think-lang, X-Trace-Id, X-Client-Type';

    private const ALLOW_METHODS = 'GET, POST, PUT, DELETE, OPTIONS';

    public function process(Request $request, callable $handler): HttpResponse
    {
        $origin = (string) $request->header('origin', '');
        $allowed = $origin !== '' && in_array($origin, (array) config('cors.allowed_origins', []), true);

        if ($request->method() === 'OPTIONS') {
            return $allowed ? $this->withCors(new Response(204), $origin) : new Response(403);
        }

        /** @var HttpResponse $response */
        $response = $handler($request);

        return $allowed ? $this->withCors($response, $origin) : $response;
    }

    private function withCors(HttpResponse $response, string $origin): HttpResponse
    {
        $response->withHeaders([
            'Access-Control-Allow-Origin'      => $origin,
            'Vary'                             => 'Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers'     => self::ALLOW_HEADERS,
            'Access-Control-Allow-Methods'     => self::ALLOW_METHODS,
            'Access-Control-Expose-Headers'    => 'X-Trace-Id',
        ]);

        return $response;
    }
}
