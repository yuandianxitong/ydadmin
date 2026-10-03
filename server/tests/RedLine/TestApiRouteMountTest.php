<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\ApiAuthMiddleware;
use app\middleware\CorsMiddleware;
use app\middleware\InstallGuardMiddleware;
use app\middleware\LocaleMiddleware;
use app\middleware\LoginRateLimitMiddleware;
use app\middleware\RequestContextMiddleware;
use tests\Support\RouteStack;
use tests\TestCase;
use Webman\Route;
use Webman\Route\Route as RouteObject;

/** 红线：C 端 /api 默认要登录。公开白名单之外，每条 /api 路由都必须带 ApiAuthMiddleware；白名单里的不得带。 */
final class TestApiRouteMountTest extends TestCase
{
    /**
     * C 端公开白名单（规格第 7 节，26 条，格式「方法 路径」）。新增公开路由必须同时改这里，并在评审里说明理由。
     */
    private const PUBLIC_ROUTES = [
        'POST /api/common/sms-code',
        'GET /api/common/config',
        'GET /api/article/list',
        'GET /api/article/detail/{id:\d+}',
        'GET /api/article-category/list',
        'GET /api/region/tree',
        'GET /api/region/children',
        'GET /api/version/check',
        'GET /api/mobile/diy-page',
        'GET /api/mobile/config',
        'GET /api/announcement/list',
        'GET /api/announcement/detail/{id:\d+}',
        'GET /api/agreement/{code:[a-z][a-z0-9_]{1,49}}',
        'POST /api/auth/login',
        'POST /api/auth/register',
        'POST /api/auth/sms-login',
        'POST /api/auth/wechat-web-login',
        'POST /api/auth/wechat-login',
        'POST /api/auth/wechat-quick-login',
        'POST /api/auth/wechat-bindphone',
        'POST /api/auth/wechat-h5-login',
        'GET /api/wechat/oauth-url',
        'GET /api/wechat/serve',
        'POST /api/wechat/serve',
        'POST /api/payment/notify/wechat',
        'POST /api/payment/notify/alipay',
    ];

    private const BUSINESS_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    /** @return list<RouteObject> */
    private function apiRoutes(): array
    {
        self::ensureRoutesLoaded();

        return array_values(array_filter(
            Route::getRoutes(),
            static fn (RouteObject $route): bool => str_starts_with($route->getPath(), '/api'),
        ));
    }

    /** @return list<string> 「方法 路径」键，只含业务方法（不含 HEAD / OPTIONS） */
    private function keysOf(RouteObject $route): array
    {
        $keys = [];
        foreach ($route->getMethods() as $method) {
            if (in_array($method, self::BUSINESS_METHODS, true)) {
                $keys[] = $method . ' ' . $route->getPath();
            }
        }

        return $keys;
    }

    public function test_public_whitelist_is_26_entries(): void
    {
        $this->assertCount(26, self::PUBLIC_ROUTES);
        $this->assertSame(self::PUBLIC_ROUTES, array_values(array_unique(self::PUBLIC_ROUTES)));
    }

    public function test_every_non_public_api_route_requires_api_auth(): void
    {
        $offenders = [];
        $checked = 0;
        foreach ($this->apiRoutes() as $route) {
            $keys = $this->keysOf($route);
            if ($keys === [] || array_intersect($keys, self::PUBLIC_ROUTES) !== []) {
                continue;
            }
            $checked++;
            if (!in_array(ApiAuthMiddleware::class, RouteStack::outerToInner($route), true)) {
                $offenders[] = implode('|', $keys);
            }
        }

        $this->assertGreaterThanOrEqual(19, $checked, '认证段：auth 3、user 9、payment 1、message 3、feedback 3、upload 1');
        $this->assertSame([], $offenders, "以下 /api 路由没有 ApiAuthMiddleware：\n" . implode("\n", $offenders));
    }

    public function test_public_whitelist_exists_and_carries_no_api_auth(): void
    {
        $seen = [];
        foreach ($this->apiRoutes() as $route) {
            foreach ($this->keysOf($route) as $key) {
                if (in_array($key, self::PUBLIC_ROUTES, true)) {
                    $seen[] = $key;
                    $this->assertNotContains(ApiAuthMiddleware::class, RouteStack::outerToInner($route), "{$key} 是公开路由，不得挂 ApiAuthMiddleware");
                }
            }
        }
        $missing = array_diff(self::PUBLIC_ROUTES, $seen);
        $this->assertSame([], array_values($missing), '白名单里的公开路由不存在：' . implode(', ', $missing));
    }

    public function test_member_profile_stack_is_outer_four_then_api_auth(): void
    {
        $this->assertSame([
            InstallGuardMiddleware::class,
            RequestContextMiddleware::class,
            LocaleMiddleware::class,
            CorsMiddleware::class,
            ApiAuthMiddleware::class,
        ], RouteStack::outerToInner($this->routeBy('GET', '/api/user/profile')));
    }

    public function test_member_login_has_rate_limit_and_no_api_auth(): void
    {
        $names = RouteStack::outerToInner($this->routeBy('POST', '/api/auth/login'));
        $this->assertSame(LoginRateLimitMiddleware::class, $names[4] ?? null);
        $this->assertNotContains(ApiAuthMiddleware::class, $names);
    }

    public function test_member_upload_requires_api_auth(): void
    {
        $names = RouteStack::outerToInner($this->routeBy('POST', '/api/common/upload/image'));
        $this->assertContains(ApiAuthMiddleware::class, $names);
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
}
