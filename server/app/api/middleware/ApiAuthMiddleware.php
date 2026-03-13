<?php
declare(strict_types=1);

namespace app\api\middleware;

use core\http\Middleware;
use core\auth\TokenManager;
use core\exception\AuthException;
use Closure;
use think\Request;
use think\Response;

class ApiAuthMiddleware extends Middleware
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

            // 检查是否为用户端Token
            if (($payload['type'] ?? '') !== 'user') {
                return $this->errorResponse(lang('auth.token_invalid'), 401);
            }

            $request->userId = (int)($payload['user_id'] ?? 0);

        } catch (AuthException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        }

        return $next($request);
    }
}
