<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\datascope\DataScope;
use support\Db;
use tests\Support\ApiTestCase;

/** 红线：按 ID 访问范围外的记录一律当作不存在（spec §5.3），读写都不生效。 */
final class Test7_DataScopeOutOfScopeIdTest extends ApiTestCase
{
    public function test_out_of_scope_ids_behave_as_not_found(): void
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();
        $viewer = $this->actingAsAdmin(
            ['system.admin.list', 'system.admin.update', 'system.admin.delete', 'system.admin.status'],
            ['department_id' => $deptA],
            ['data_scope' => DataScope::DEPT]
        );
        $outside = $this->actingAsAdmin([], ['department_id' => $deptB, 'nickname' => '范围外']);
        $inside = $this->actingAsAdmin([], ['department_id' => $deptA]);
        $base = '/adminapi/system/admin';

        $this->get("{$base}/{$outside->id}", [], $viewer->token)->assertCode(404);
        $this->put("{$base}/{$outside->id}", ['nickname' => '越权修改'], $viewer->token)->assertCode(404);
        $this->put("{$base}/{$outside->id}/status", ['status' => 0], $viewer->token)->assertCode(404);
        $this->put("{$base}/{$outside->id}/reset-password", ['password' => 'Hijack#123'], $viewer->token)->assertCode(404);
        $this->delete("{$base}/{$outside->id}", [], $viewer->token)->assertCode(404);

        $row = Db::table('admins')->where('id', $outside->id)->first();
        $this->assertNull($row->deleted_at);
        $this->assertSame(1, (int) $row->status);
        $this->assertSame('范围外', $row->nickname);
        $this->get('/adminapi/auth/info', [], $outside->token)->assertOk(); // 越权请求没有让目标的 token 失效

        $this->get("{$base}/{$inside->id}", [], $viewer->token)->assertOk();
    }
}
