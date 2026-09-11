<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;

/**
 * 常驻内存下中间件、服务都是单例，会被连续请求复用：上一个请求的身份与权限判定不能影响下一个请求。
 * 经 Webman\App::onMessage 走真实的请求边界（webman 在每个响应发出后销毁 Context）。
 */
final class Test4_RequestStateIsolationTest extends ApiTestCase
{
    public function test_identity_does_not_carry_over_between_requests(): void
    {
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();

        $this->assertSame($a->id, $this->get('/adminapi/auth/info', [], $a->token)->assertOk()->data()['admin']['id']);
        $this->get('/adminapi/auth/info')->assertCode(401);
        $this->assertSame($b->id, $this->get('/adminapi/auth/info', [], $b->token)->assertOk()->data()['admin']['id']);
    }

    public function test_permission_decision_is_per_admin_on_the_same_worker(): void
    {
        $granted = $this->actingAsAdmin(['system.admin.list']);
        $denied = $this->actingAsAdmin();

        $this->get('/adminapi/system/admin', [], $granted->token)->assertOk();
        $this->get('/adminapi/system/admin', [], $denied->token)->assertCode(403);
        $this->get('/adminapi/system/admin', [], $granted->token)->assertOk();
    }
}
