<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\AdminPermissionMiddleware;
use app\middleware\ApiAuthMiddleware;
use core\response\Api;

/**
 * 若路由组被误配成 [ApiAuthMiddleware, AdminPermissionMiddleware]，C 端用户的数字 ID
 * 绝不能被当作管理员 ID：AdminPermissionMiddleware 只信任 RequestContext::actingUser()
 * （只有 AdminAuthMiddleware 会写），ApiAuthMiddleware 不写它。
 */
final class Test5_CEndIdentityNotAdminTest extends RedLineCase
{
    private function dispatch(string $action): int
    {
        $api = new ApiAuthMiddleware();
        // 检查器把 42 视为超管——如果权限中间件误读了 C 端身份，这两个用例就会被放行
        $perm = new AdminPermissionMiddleware(new RlChecker([42]));

        $request = $this->request('/adminapi/rl', $this->userToken(42));
        $request->controller = RlController::class;
        $request->action = $action;

        $response = $api->process($request, fn ($r) => $perm->process($r, fn () => Api::success()));

        return $this->code($response);
    }

    public function test_user_scope_id_treated_as_super_admin_by_checker_is_still_401_on_annotated(): void
    {
        $this->assertSame(401, $this->dispatch('annotated'));
    }

    public function test_user_scope_id_treated_as_super_admin_by_checker_is_still_401_on_bare(): void
    {
        $this->assertSame(401, $this->dispatch('bare'));
    }
}
