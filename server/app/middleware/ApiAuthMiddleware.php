<?php

declare(strict_types=1);

namespace app\middleware;

use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\exception\AuthException;
use core\response\Api;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * C 端认证：只接受 user scope 的 token。不设置 acting user（它只代表管理员）。
 *
 * 每次请求比对一次 token 版本号（一次 Redis GET，M5a spec §4.2）：管理端把会员改成禁用、
 * 会员自己改密码都会 bump，已签发的 token 立即失效。不带 ver 的 token 按 0 比对，一律拒绝——
 * 与 AdminAuthMiddleware 同样 fail closed，Redis 丢键重新播种后全员重新登录，被吊销的 token 不会复活。
 */
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

        // 禁用、改密码会让版本号自增，旧 token 立即失效（M5a spec §4.2）
        if ((int) ($data['ver'] ?? 0) !== TokenVersion::current($userId, 'user')) {
            return Api::error(lang('auth.token_expired'), 401);
        }

        $request->userId = $userId;

        return $handler($request);
    }
}
