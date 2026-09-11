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

    public function test_unknown_api_is_not_routed(): void
    {
        $this->assertSame(Dispatcher::NOT_FOUND, Route::dispatch('GET', '/adminapi/nope')[0]);
    }
}
