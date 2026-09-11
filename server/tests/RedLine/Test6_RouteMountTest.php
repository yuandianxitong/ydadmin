<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminPermissionMiddleware;
use app\middleware\CorsMiddleware;
use app\middleware\LocaleMiddleware;
use app\middleware\RequestContextMiddleware;
use tests\TestCase;
use Webman\Route;
use Webman\Route\Route as RouteObject;

/** 红线：默认拒绝完全依赖路由有没有挂对组。/adminapi 下除公开白名单外，每条路由都必须挂认证 + 权限中间件。 */
final class Test6_RouteMountTest extends TestCase
{
    /** 公开路由白名单：与 config/route.php 的公开区一致。新增公开路由必须同时改这里，并在评审里说明理由。 */
    private const PUBLIC_ROUTES = ['/adminapi/health', '/adminapi/auth/captcha', '/adminapi/auth/login'];

    /** @return list<RouteObject> */
    private function adminapiRoutes(): array
    {
        self::ensureRoutesLoaded();

        return array_values(array_filter(Route::getRoutes(), static fn (RouteObject $route): bool => str_starts_with($route->getPath(), '/adminapi')));
    }

    public function test_every_non_public_route_requires_auth_and_permission(): void
    {
        $offenders = [];
        $checked = 0;
        foreach ($this->adminapiRoutes() as $route) {
            if (in_array($route->getPath(), self::PUBLIC_ROUTES, true)) {
                continue;
            }
            $checked++;
            $middlewares = $route->getMiddleware();
            if (!in_array(AdminAuthMiddleware::class, $middlewares, true) || !in_array(AdminPermissionMiddleware::class, $middlewares, true)) {
                $offenders[] = implode('|', $route->getMethods()) . ' ' . $route->getPath();
            }
        }

        $this->assertGreaterThanOrEqual(42, $checked, 'M1a 共 42 条认证路由（auth 3、admin 10、role 12、menu 9、department 7、config 1）');
        $this->assertSame([], $offenders, "以下路由没有挂认证 + 权限中间件：\n" . implode("\n", $offenders));
    }

    public function test_public_routes_exist_and_carry_no_auth(): void
    {
        $byPath = [];
        foreach ($this->adminapiRoutes() as $route) {
            $byPath[$route->getPath()] = $route->getMiddleware();
        }
        foreach (self::PUBLIC_ROUTES as $path) {
            $this->assertArrayHasKey($path, $byPath, "公开路由 {$path} 不存在");
            $this->assertNotContains(AdminAuthMiddleware::class, $byPath[$path]);
        }
    }

    public function test_every_adminapi_route_carries_the_outer_middlewares(): void
    {
        foreach ($this->adminapiRoutes() as $route) {
            foreach ([RequestContextMiddleware::class, LocaleMiddleware::class, CorsMiddleware::class] as $middleware) {
                $this->assertContains($middleware, $route->getMiddleware(), "{$route->getPath()} 缺少 {$middleware}");
            }
        }
    }
}
