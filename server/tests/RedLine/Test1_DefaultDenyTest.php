<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\AdminPermissionMiddleware;
use core\response\Api;
use support\Container;

final class Test1_DefaultDenyTest extends RedLineCase
{
    private function dispatch(AdminPermissionMiddleware $middleware, string $action, int $adminId): int
    {
        $request = $this->request('/adminapi/rl');
        $request->controller = RlController::class;
        $request->action = $action;
        $request->userId = $adminId;

        return $this->code($middleware->process($request, fn () => Api::success()));
    }

    public function test_container_default_denies_everything_but_skip(): void
    {
        // 走真实容器绑定（M0 = DenyAllChecker），确认安全默认值确实生效
        $middleware = Container::get(AdminPermissionMiddleware::class);

        $this->assertSame(403, $this->dispatch($middleware, 'bare', 1));
        $this->assertSame(403, $this->dispatch($middleware, 'annotated', 1));
        $this->assertSame(200, $this->dispatch($middleware, 'skipped', 1));
    }

    public function test_unannotated_action_is_denied_even_with_every_permission_granted(): void
    {
        $middleware = new AdminPermissionMiddleware(new RlChecker([], [2 => ['rl.item.list', '*']]));

        $this->assertSame(403, $this->dispatch($middleware, 'bare', 2), '无注解的方法只有超管能访问');
        $this->assertSame(200, $this->dispatch($middleware, 'annotated', 2));
    }

    public function test_only_super_admin_reaches_unannotated_action(): void
    {
        $middleware = new AdminPermissionMiddleware(new RlChecker([1]));

        $this->assertSame(200, $this->dispatch($middleware, 'bare', 1));
        $this->assertSame(403, $this->dispatch($middleware, 'bare', 3));
    }
}
