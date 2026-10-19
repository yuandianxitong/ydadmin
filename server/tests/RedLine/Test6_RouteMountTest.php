<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\AdminPermissionMiddleware;
use app\middleware\CorsMiddleware;
use app\middleware\IdempotencyMiddleware;
use app\middleware\InstallGuardMiddleware;
use app\middleware\LocaleMiddleware;
use app\middleware\LoginRateLimitMiddleware;
use app\middleware\RequestContextMiddleware;
use app\middleware\TelemetryMiddleware;
use tests\Support\RouteStack;
use tests\TestCase;
use Webman\Middleware;
use Webman\Route;
use Webman\Route\Route as RouteObject;

/** 红线：默认拒绝与操作审计完全依赖路由有没有挂对组。/adminapi 下除公开白名单外，每条路由都必须挂认证 + 权限 + 操作日志中间件。 */
final class Test6_RouteMountTest extends TestCase
{
    /**
     * 公开路由白名单：与 config/route.php 的公开区一致。新增公开路由必须同时改这里，并在评审里说明理由。
     *
     * API 文档在生产与开发环境都走管理端认证栈，不属于公开白名单。
     */
    private const PUBLIC_ROUTES = [
        '/adminapi/health',
        // 只公开依赖布尔状态，不公开队列、SQL 或进程详情。
        '/adminapi/health/ready',
        '/adminapi/auth/captcha',
        '/adminapi/auth/login',
    ];

    /** @return list<RouteObject> */
    private function adminapiRoutes(): array
    {
        self::ensureRoutesLoaded();

        return array_values(array_filter(Route::getRoutes(), static fn (RouteObject $route): bool => str_starts_with($route->getPath(), '/adminapi')));
    }

    public function test_every_non_public_route_carries_the_whole_auth_group(): void
    {
        $required = [AdminAuthMiddleware::class, AdminPermissionMiddleware::class, AdminLogMiddleware::class];
        $offenders = [];
        $checked = 0;
        foreach ($this->adminapiRoutes() as $route) {
            if (in_array($route->getPath(), self::PUBLIC_ROUTES, true)) {
                continue;
            }
            $checked++;
            $missing = array_diff($required, RouteStack::outerToInner($route));
            if ($missing !== []) {
                $short = array_map(static fn (string $class): string => substr((string) strrchr($class, '\\'), 1), $missing);
                $offenders[] = implode('|', $route->getMethods()) . ' ' . $route->getPath() . ' 缺少 ' . implode(', ', $short);
            }
        }

        $this->assertGreaterThanOrEqual(83, $checked, 'M1a 41 条（auth 3、admin 10、role 12、menu 9、department 7）+ M1b 34 条（config 7、dictionary 12、log 6、notification 9）+ M1c 8 条（file 6、upload 2）');
        $this->assertSame([], $offenders, "以下路由没有挂完整的认证组：\n" . implode("\n", $offenders));
    }

    public function test_public_routes_exist_and_carry_no_auth(): void
    {
        $byPath = [];
        foreach ($this->adminapiRoutes() as $route) {
            $byPath[$route->getPath()] = RouteStack::outerToInner($route);
        }
        foreach (self::PUBLIC_ROUTES as $path) {
            $this->assertArrayHasKey($path, $byPath, "公开路由 {$path} 不存在");
            $this->assertNotContains(AdminAuthMiddleware::class, $byPath[$path]);
            $this->assertNotContains(AdminLogMiddleware::class, $byPath[$path]);
        }
    }

    public function test_menu_index_stack_has_telemetry_and_idempotency_after_authorization(): void
    {
        $route = $this->routeBy('GET', '/adminapi/system/menu');
        $this->assertSame([
            InstallGuardMiddleware::class,
            RequestContextMiddleware::class,
            LocaleMiddleware::class,
            CorsMiddleware::class,
            TelemetryMiddleware::class,
            AdminAuthMiddleware::class,
            AdminPermissionMiddleware::class,
            IdempotencyMiddleware::class,
            AdminLogMiddleware::class,
        ], RouteStack::outerToInner($route));
        $this->assertSame(
            // 认证控制器按方法挂认证栈，info 没有需要幂等回放的写动作。
            array_values(array_diff(RouteStack::outerToInner($route), [IdempotencyMiddleware::class])),
            RouteStack::outerToInner($this->routeBy('GET', '/adminapi/auth/info')),
        );
    }

    public function test_region_tree_and_common_regions_share_the_admin_stack(): void
    {
        $expected = [
            InstallGuardMiddleware::class,
            RequestContextMiddleware::class,
            LocaleMiddleware::class,
            CorsMiddleware::class,
            TelemetryMiddleware::class,
            AdminAuthMiddleware::class,
            AdminPermissionMiddleware::class,
            IdempotencyMiddleware::class,
            AdminLogMiddleware::class,
        ];
        $this->assertSame($expected, RouteStack::outerToInner($this->routeBy('GET', '/adminapi/region/tree')));
        $this->assertSame($expected, RouteStack::outerToInner($this->routeBy('GET', '/adminapi/common/regions')));
    }

    private function routeBy(string $method, string $path): RouteObject
    {
        self::ensureRoutesLoaded();
        foreach (Route::getRoutes() as $route) {
            if ($route->getPath() === $path && in_array($method, $route->getMethods(), true)) {
                return $route;
            }
        }
        $this->fail("找不到 {$method} {$path}");
    }

    public function test_health_stack_has_only_global_middleware(): void
    {
        $this->assertSame([
            InstallGuardMiddleware::class,
            RequestContextMiddleware::class,
            LocaleMiddleware::class,
            CorsMiddleware::class,
            TelemetryMiddleware::class,
        ], RouteStack::outerToInner($this->routeBy('GET', '/adminapi/health')));
    }

    public function test_admin_login_has_rate_limit_and_no_auth(): void
    {
        $names = RouteStack::outerToInner($this->routeBy('POST', '/adminapi/auth/login'));
        $this->assertSame(LoginRateLimitMiddleware::class, $names[5] ?? null);
        $this->assertNotContains(AdminAuthMiddleware::class, $names);
    }

    public function test_spa_home_stack_has_only_global_middleware(): void
    {
        $this->assertSame([
            InstallGuardMiddleware::class,
            RequestContextMiddleware::class,
            LocaleMiddleware::class,
            CorsMiddleware::class,
            TelemetryMiddleware::class,
        ], RouteStack::outerToInner($this->routeBy('GET', '/')));
    }

    public function test_fallback_runs_cors_without_the_global_stack(): void
    {
        self::ensureRoutesLoaded();
        $property = new \ReflectionProperty(Route::class, 'fallbackRoutes');
        $stored = $property->getValue();
        $this->assertArrayHasKey('', $stored);
        $route = $stored[''];
        // 框架组装 fallback 时关掉全局中间件，RouteStack 那条算法对不上真实调用。
        $stack = Middleware::getMiddleware('', 'NOT_FOUND', $route->getCallback(), $route, false);
        $names = [];
        foreach ($stack as $item) {
            $names[] = is_array($item) ? (string) $item[0] : (string) $item;
        }

        $this->assertSame([CorsMiddleware::class], array_reverse($names));
    }
}
