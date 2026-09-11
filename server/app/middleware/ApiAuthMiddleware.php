<?php

declare(strict_types=1);

namespace app\middleware;

use core\auth\TokenManager;
use core\exception\AuthException;
use core\response\Api;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** C 端认证：只接受 user scope 的 token。不设置 acting user（它只代表管理员）。 */
class ApiAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $mgr = TokenManager::scope('user');
        $token = $mgr->getTokenFromHeader($request);
        if ($token === null) {
            return Api::error(lang('auth.please_login'), 401);
        }

        try {
            $data = $mgr->verify($token);
        } catch (AuthException $e) {
            return Api::error($e->getMessage(), 401);
        }

        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            return Api::error(lang('auth.token_invalid'), 401);
        }
        $request->userId = $userId;

        return $handler($request);
    }
}
