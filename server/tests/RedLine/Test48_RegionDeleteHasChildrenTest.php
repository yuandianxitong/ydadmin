<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线（M7b）：有子级的地区不能删。
 *
 * 走管理端 API 建父子两行后 DELETE 父级必须 400，且父行仍在。
 */
final class Test48_RegionDeleteHasChildrenTest extends ApiTestCase
{
    public function test_cannot_delete_region_that_has_children(): void
    {
        $admin = $this->actingAsAdmin('super');
        $suffix = bin2hex(random_bytes(4));

        $parent = $this->post('/adminapi/region', [
            'parent_id' => 0,
            'name'      => 'rl48-p-' . $suffix,
            'code'      => 'rl48p' . $suffix,
            'status'    => 1,
        ], $admin->token)->assertOk()->data();
        $this->track('regions', (int) $parent['id']);

        $child = $this->post('/adminapi/region', [
            'parent_id' => $parent['id'],
            'name'      => 'rl48-c-' . $suffix,
            'code'      => 'rl48c' . $suffix,
            'status'    => 1,
        ], $admin->token)->assertOk()->data();
        $this->track('regions', (int) $child['id']);

        $this->delete('/adminapi/region/' . $parent['id'], [], $admin->token)->assertCode(400);
        $this->assertTrue(Db::table('regions')->where('id', $parent['id'])->exists());
    }
}
