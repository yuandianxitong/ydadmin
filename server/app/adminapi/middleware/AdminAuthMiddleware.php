<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\middleware;

use core\http\Middleware;
use core\auth\TokenManager;
use core\exception\AuthException;
use Closure;
use think\Request;
use think\Response;

class AdminAuthMiddleware extends Middleware
{
    protected TokenManager $tokenManager;

    public function __construct()
    {
        $this->tokenManager = app()->make(TokenManager::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokenManager->getTokenFromHeader();

        if (!$token) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        try {
            $payload = $this->tokenManager->verify($token);

            // 检查是否为管理员Token
            if (($payload['type'] ?? '') !== 'admin') {
                return $this->errorResponse(lang('auth.token_invalid'), 401);
            }

            // 将用户信息注入请求（强类型以适配 strict_types=1）
            $request->userId = (int)($payload['admin_id'] ?? 0);
            $request->username = (string)($payload['username'] ?? '');

        } catch (AuthException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        }

        return $next($request);
    }
}
