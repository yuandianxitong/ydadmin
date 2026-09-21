<?php

declare(strict_types=1);

namespace tests\Feature;

use FastRoute\Dispatcher;
use tests\TestCase;
use Webman\Route;

final class RouteTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::ensureRoutesLoaded();
    }

    public function test_health_route_is_registered(): void
    {
        $this->assertSame(Dispatcher::FOUND, Route::dispatch('GET', '/adminapi/health')[0]);
    }

    public function test_spa_routes_cover_root_and_deep_paths(): void
    {
        foreach (['/', '/admin', '/admin/', '/admin/system/admin', '/pc/article/1', '/mobile/', '/mobile/pages/index'] as $path) {
            $this->assertSame(Dispatcher::FOUND, Route::dispatch('GET', $path)[0], $path);
        }
    }

    public function test_install_wizard_accepts_trailing_slash(): void
    {
        $this->assertSame(Dispatcher::FOUND, Route::dispatch('GET', '/install')[0], '/install');
        $this->assertSame(Dispatcher::FOUND, Route::dispatch('GET', '/install/')[0], '/install/');
    }

    public function test_unknown_api_is_not_routed(): void
    {
        $this->assertSame(Dispatcher::NOT_FOUND, Route::dispatch('GET', '/adminapi/nope')[0]);
    }

    /**
     * 每条 adminapi 路由的鉴权都假定 webman 的「/控制器/方法」默认路由已关闭：
     * 一旦这行被移除或失效，未注册的 /adminapi/<ctrl>/<action> 会绕过路由组中间件直达控制器方法。
     * Route::dispatch 从不触发默认路由，所以只能直接断言开关状态。
     */
    public function test_default_route_is_disabled_for_main_app(): void
    {
        $this->assertTrue(Route::isDefaultRouteDisabled('', '*'));
    }
}
