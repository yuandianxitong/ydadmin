<?php

declare(strict_types=1);

namespace app\middleware;

use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\context\RequestContext;
use core\exception\AuthException;
use core\response\Api;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 管理端认证：只接受 admin scope 的 token。
 * 中间件是容器单例，身份只能写到本次的 $request 与 support\Context，不得写实例属性。
 */
class AdminAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->getTokenFromHeader($request);
        if ($token === null) {
            return Api::error(lang('auth.please_login'), 401);
        }

        try {
            $claims = $mgr->verifyClaims($token);
        } catch (AuthException $e) {
            return Api::error($e->getMessage(), 401);
        }
        $data = $claims['payload'];

        $adminId = (int) ($data['admin_id'] ?? 0);
        if ($adminId <= 0) {
            return Api::error(lang('auth.token_invalid'), 401);
        }

        // 禁用、删除、改密码等会让版本号自增，旧 token 立即失效（spec §4.4）
        if ((int) ($data['ver'] ?? 0) !== TokenVersion::current($adminId)) {
            return Api::error(lang('auth.token_expired'), 401);
        }

        $request->userId = $adminId;
        $request->username = (string) ($data['username'] ?? '');
        // M4：WS 票据要记下本次 token 的版本号、jti、会话 id 与会话绝对到期时间，
        // WS 进程据此判断连接是否已被吊销 / 登出 / 超过登录时长上限（旧 token 没有 sid 时为空串）
        $request->tokenVer = (int) ($data['ver'] ?? 0);
        $request->tokenJti = $claims['jti'];
        $request->tokenSid = is_string($data['sid'] ?? null) ? $data['sid'] : '';
        $request->tokenSessionExpiresAt = $claims['session_expires_at'];
        RequestContext::setActingUser($adminId);

        return $handler($request);
    }
}
