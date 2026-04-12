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
use core\auth\Permission as PermissionChecker;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\service\system\AdminService;
use Closure;
use ReflectionMethod;
use think\facade\Log;
use think\Request;
use think\Response;

/**
 * 管理后台权限中间件
 *
 * 通过反射读取控制器方法上的 #[Permission('xxx')] 注解来确定所需权限，
 * 替代硬编码的权限映射表。
 *
 * 权限解析优先级：
 *   1. #[PermissionSkip] → 跳过权限检查
 *   2. #[Permission('xxx')] → 校验指定权限
 *   3. 无注解 → 仅已登录可访问（放行但记录警告日志，提醒开发者补充注解）
 *
 * 开发者扩展新模块时，只需在控制器方法上添加 #[Permission] 注解即可，
 * 无需修改中间件代码。不需要权限检查的方法请显式标注 #[PermissionSkip]。
 */
class AdminPermissionMiddleware extends Middleware
{
    protected PermissionChecker $permission;
    protected AdminService $adminService;

    /**
     * 反射结果缓存（进程级，避免同一请求内重复反射）
     * key: "ControllerClass::method"  value: permission string ('' = skip)
     */
    protected static array $permissionCache = [];

    public function __construct()
    {
        $this->permission = app()->make(PermissionChecker::class);
        $this->adminService = app()->make(AdminService::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->userId ?? 0;

        if (!$userId) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        // 通过反射解析权限标识
        $permissionName = $this->resolvePermission($request);

        if (!$permissionName) {
            // PermissionSkip / 无注解路由也注入 userInfo，确保 Controller 可用
            try {
                $request->userInfo = $this->adminService->getAdminInfo($userId);
            } catch (\Throwable) {
                $request->userInfo = [];
            }
            return $next($request);
        }

        try {
            // 检查用户权限
            if (!$this->permission->check($userId, $permissionName)) {
                return $this->errorResponse(lang('auth.permission_denied'), 403);
            }

            // 获取用户信息并注入请求
            $adminInfo = $this->adminService->getAdminInfo($userId);
            $request->userInfo = $adminInfo;

        } catch (\core\exception\PermissionException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        return $next($request);
    }

    /**
     * 通过反射解析控制器方法上的 #[Permission] 注解
     *
     * 从 ThinkPHP 路由规则中提取控制器类名和方法名，
     * 然后通过 ReflectionMethod 读取 PHP 8 属性。
     */
    protected function resolvePermission(Request $request): string
    {
        try {
            $rule = $request->rule();
            if (!$rule) {
                return '';
            }

            // 获取路由调度字符串，格式如：'v1.system.AdminController/index'
            $dispatch = $rule->getRoute();

            if (!is_string($dispatch) || !str_contains($dispatch, '/')) {
                return '';
            }

            [$controllerPath, $action] = explode('/', $dispatch, 2);

            // 转换为完整类名：v1.system.AdminController → app\adminapi\controller\v1\system\AdminController
            $controllerClass = 'app\\adminapi\\controller\\' . str_replace('.', '\\', $controllerPath);

            // 查缓存
            $cacheKey = $controllerClass . '::' . $action;
            if (array_key_exists($cacheKey, static::$permissionCache)) {
                return static::$permissionCache[$cacheKey];
            }

            // 类或方法不存在，跳过
            if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
                return static::$permissionCache[$cacheKey] = '';
            }

            $ref = new ReflectionMethod($controllerClass, $action);

            // 1. #[PermissionSkip] → 显式跳过
            if (!empty($ref->getAttributes(PermissionSkip::class))) {
                return static::$permissionCache[$cacheKey] = '';
            }

            // 2. #[Permission('xxx')] → 使用声明的权限标识
            $permAttrs = $ref->getAttributes(Permission::class);
            if (!empty($permAttrs)) {
                /** @var Permission $perm */
                $perm = $permAttrs[0]->newInstance();
                return static::$permissionCache[$cacheKey] = $perm->value;
            }

            // 3. 无注解 → 放行但记录警告，提醒开发者补充注解
            Log::warning("Controller method missing #[Permission] or #[PermissionSkip] annotation: {$cacheKey}");
            return static::$permissionCache[$cacheKey] = '';

        } catch (\Throwable) {
            // 反射异常，安全放行（避免因框架变更导致全部拦截）
            return '';
        }
    }
}
