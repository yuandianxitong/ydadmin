<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;

/**
 * 红线（M7b）：C 端版本检查与地区树对未登录公开。
 *
 * 未带 token 的 GET /api/version/check、/api/region/tree 不得 401。
 * 200 即可；库里没有更高版本时 check 的 need_update 为 false。
 */
final class Test46_CEndRegionVersionPublicTest extends ApiTestCase
{
    public function test_guest_can_check_version_and_read_region_tree(): void
    {
        $check = $this->get('/api/version/check', ['platform' => 'android', 'version_code' => 1]);
        $this->assertNotSame(401, $check->code(), 'C 端版本检查不得对未登录返回 401');
        $check->assertOk();
        $this->assertFalse($check->data()['need_update']);

        $tree = $this->get('/api/region/tree');
        $this->assertNotSame(401, $tree->code(), 'C 端地区树不得对未登录返回 401');
        $tree->assertOk();
    }
}
