<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;

/**
 * 红线（M7c）：管理端装修首页必须登录。
 *
 * 未带 token 的 GET /adminapi/diy/home 必须 401。
 */
final class Test51_AdminDiyRequiresAuthTest extends ApiTestCase
{
    public function test_unauthenticated_admin_home_is_401(): void
    {
        $this->get('/adminapi/diy/home')->assertCode(401);
    }
}
