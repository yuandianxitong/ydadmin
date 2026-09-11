<?php

declare(strict_types=1);

namespace tests\Feature\System;

use tests\Support\ApiTestCase;

final class MenuRoutesTest extends ApiTestCase
{
    public function test_menu_routes_equals_auth_info_routes(): void
    {
        foreach (['super', ['system', 'system.admin.list', 'system.admin.create']] as $permissions) {
            $admin = $this->actingAsAdmin($permissions);
            $info = $this->get('/adminapi/auth/info', [], $admin->token)->assertOk()->data();
            $routes = $this->get('/adminapi/system/menu/routes', [], $admin->token)->assertOk()->data();

            $this->assertSame($info['routes'], $routes);
        }
    }

    public function test_limited_admin_only_sees_granted_menus(): void
    {
        $admin = $this->actingAsAdmin(['system', 'system.admin.list', 'system.admin.create']);
        $routes = $this->get('/adminapi/system/menu/routes', [], $admin->token)->assertOk()->data();

        $this->assertSame([2], array_column($routes, 'id'));
        $this->assertSame([10], array_column($routes[0]['children'], 'id'), '按钮不进路由树，未授权的菜单不出现');
    }

    public function test_requires_login(): void
    {
        $this->get('/adminapi/system/menu/routes')->assertCode(401);
    }
}
