<?php
declare(strict_types=1);

namespace app\adminapi\middleware;

use think\Request;
use think\Response;

class CorsMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $origin = $request->header('Origin', '');
        $allowedOrigin = $this->resolveAllowedOrigin($origin);

        $corsHeaders = [
            'Access-Control-Allow-Origin'      => $allowedOrigin,
            'Access-Control-Allow-Methods'      => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers'      => 'Authorization, Content-Type, X-Requested-With, Accept, Origin, X-Trace-Id, think-lang',
            'Access-Control-Allow-Credentials'  => 'true',
        ];

        // OPTIONS 预检请求直接返回
        if ($request->method(true) === 'OPTIONS') {
            return response('', 204)
                ->header(array_merge($corsHeaders, ['Access-Control-Max-Age' => '86400']));
        }

        /** @var Response $response */
        $response = $next($request);
        $response->header($corsHeaders);

        return $response;
    }

    /**
     * 解析允许的 Origin：优先读取 .env 中的 CORS_ALLOWED_ORIGINS 配置，
     * 未配置时在 debug 模式下允许 localhost，生产环境拒绝所有跨域。
     */
    private function resolveAllowedOrigin(string $origin): string
    {
        if ($origin === '') {
            return '';
        }

        // 从 .env 读取白名单（逗号分隔），如：CORS_ALLOWED_ORIGINS=https://admin.example.com,https://www.example.com
        $allowedList = env('CORS_ALLOWED_ORIGINS', '');
        if ($allowedList !== '') {
            $allowed = array_map('trim', explode(',', $allowedList));
            if (in_array($origin, $allowed, true)) {
                return $origin;
            }
            return $allowed[0]; // 返回第一个允许的 origin 作为默认值
        }

        // 未配置白名单时：debug 模式允许 localhost
        if (env('APP_DEBUG', false)) {
            $host = parse_url($origin, PHP_URL_HOST);
            if ($host === 'localhost' || $host === '127.0.0.1') {
                return $origin;
            }
        }

        return '';
    }
}
