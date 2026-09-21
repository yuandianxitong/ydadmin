<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;

/**
 * 红线（M7b）：管理端级联选择器 GET /adminapi/common/regions 是 PermissionSkip。
 *
 * 登录但没有任何权限点的管理员必须 200，data 为数组（给 Region 组件用）。
 */
final class Test47_CommonRegionsPermissionSkipTest extends ApiTestCase
{
    public function test_logged_in_admin_without_permissions_can_read_common_regions(): void
    {
        $admin = $this->actingAsAdmin([]);
        $data = $this->get('/adminapi/common/regions', [], $admin->token)->assertOk()->data();
        $this->assertIsArray($data);
    }
}
