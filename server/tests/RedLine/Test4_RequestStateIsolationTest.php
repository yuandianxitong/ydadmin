<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminPermissionMiddleware;
use core\context\RequestContext;
use core\response\Api;
use support\Container;
use support\Context;

/**
 * 常驻内存下中间件是容器单例、会被连续请求复用：上一个请求的身份不能影响下一个请求。
 */
final class Test4_RequestStateIsolationTest extends RedLineCase
{
    public function test_identity_does_not_carry_over_to_next_request(): void
    {
        $auth = Container::get(AdminAuthMiddleware::class);
        $this->assertSame($auth, Container::get(AdminAuthMiddleware::class), '前提：中间件是容器单例');

        $requestA = $this->request('/adminapi/rl', $this->adminToken(7));
        $this->assertSame(200, $this->code($auth->process($requestA, fn () => Api::success())));
        $this->assertSame(7, RequestContext::actingUser());

        Context::destroy(); // webman 在每个请求结束时执行

        $requestB = $this->request('/adminapi/rl');
        $this->assertSame(401, $this->code($auth->process($requestB, fn () => Api::success())));
        $this->assertSame(0, RequestContext::actingUser());
        $this->assertFalse(isset($requestB->userId), '请求 B 不得继承请求 A 的身份');
    }

    public function test_permission_decision_is_per_admin_on_a_reused_instance(): void
    {
        $middleware = new AdminPermissionMiddleware(new RlChecker([], [1 => ['rl.item.list']]));
        $run = function (int $adminId) use ($middleware): int {
            $request = $this->request('/adminapi/rl');
            $request->controller = RlController::class;
            $request->action = 'annotated';
            $request->userId = $adminId;

            return $this->code($middleware->process($request, fn () => Api::success()));
        };

        $this->assertSame(200, $run(1));
        $this->assertSame(403, $run(2), '管理员 1 的放行结果不得被管理员 2 复用');
        $this->assertSame(200, $run(1));
    }
}
