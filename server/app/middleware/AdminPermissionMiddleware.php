<?php

declare(strict_types=1);

namespace app\middleware;

use core\permission\Permission;
use core\permission\PermissionCheckerInterface;
use core\permission\PermissionSkip;
use core\response\Api;
use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 管理端权限：#[PermissionSkip] 放行 → 超管放行 → 无注解拒绝 → 有注解交给检查器。
 * 与 TP8 版的差异：TP8 版对无注解的方法只打日志放行，这里默认拒绝（spec §3.5）。
 */
class AdminPermissionMiddleware implements MiddlewareInterface
{
    private const SKIP = "\0skip\0";

    /**
     * 「类@方法」→ 权限点 / SKIP / ''（无注解）的反射结果缓存。key 与值都在部署期固定，
     * 与请求无关，可安全地进程级共享（check:context 白名单）。
     *
     * @var array<string, string>
     */
    private static array $permissionCache = [];

    public function __construct(private readonly PermissionCheckerInterface $checker)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $adminId = (int) ($request->userId ?? 0);
        if ($adminId <= 0) {
            return Api::error(lang('auth.please_login'), 401);
        }

        $controller = is_string($request->controller) ? $request->controller : '';
        $action = is_string($request->action) ? $request->action : '';
        if ($controller === '' || $action === '' || !method_exists($controller, $action)) {
            return Api::error(lang('auth.permission_denied'), 403);
        }

        $permission = self::resolve($controller, $action);
        if ($permission === self::SKIP || $this->checker->isSuperAdmin($adminId)) {
            return $handler($request);
        }
        if ($permission === '' || !$this->checker->check($adminId, $permission)) {
            return Api::error(lang('auth.permission_denied'), 403);
        }

        return $handler($request);
    }

    private static function resolve(string $controller, string $action): string
    {
        $key = $controller . '@' . $action;
        if (isset(self::$permissionCache[$key])) {
            return self::$permissionCache[$key];
        }

        $method = new \ReflectionMethod($controller, $action);
        if ($method->getAttributes(PermissionSkip::class) !== []) {
            return self::$permissionCache[$key] = self::SKIP;
        }

        $attributes = $method->getAttributes(Permission::class);
        if ($attributes === []) {
            Log::warning("{$key} 缺少 #[Permission] / #[PermissionSkip] 注解，已默认拒绝（仅超管可访问）");
            return self::$permissionCache[$key] = '';
        }

        return self::$permissionCache[$key] = $attributes[0]->newInstance()->code;
    }
}
