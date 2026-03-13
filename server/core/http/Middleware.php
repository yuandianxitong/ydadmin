<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\http;

use Closure;
use think\Request;
use think\Response;

abstract class Middleware
{
    abstract public function handle(Request $request, Closure $next): Response;

    /**
     * 检查请求头
     */
    protected function checkHeader(Request $request, string $header): ?string
    {
        return $request->header($header);
    }

    /**
     * 返回错误响应
     */
    protected function errorResponse(string $message, int $code = 401): Response
    {
        return \core\http\Response::error($message, $code);
    }

    /**
     * 检查IP白名单
     */
    protected function checkIpWhitelist(Request $request, array $whitelist): bool
    {
        $clientIp = $request->ip();
        return in_array($clientIp, $whitelist) || in_array('*', $whitelist);
    }

    /**
     * 检查请求频率
     */
    protected function checkRateLimit(string $key, int $maxAttempts, int $decay): bool
    {
        $cache = cache();
        $attempts = $cache->get($key, 0);

        if ($attempts >= $maxAttempts) {
            return false;
        }

        $cache->set($key, $attempts + 1, $decay);
        return true;
    }
}
