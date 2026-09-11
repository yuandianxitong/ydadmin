<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\datascope\DataScope;
use support\Db;
use tests\Support\ApiTestCase;

/** 红线：批量操作混入范围外 ID，这些 ID 不生效，返回实际影响数（spec §5.3）。 */
final class Test8_DataScopeBatchTest extends ApiTestCase
{
    public function test_out_of_scope_ids_in_a_batch_are_ignored(): void
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();
        $viewer = $this->actingAsAdmin(['system.admin.delete'], ['department_id' => $deptA], ['data_scope' => DataScope::DEPT]);
        $inside = $this->actingAsAdmin([], ['department_id' => $deptA]);
        $outside = $this->actingAsAdmin([], ['department_id' => $deptB]);

        $data = $this->post('/adminapi/system/admin/batch-delete', ['ids' => [$inside->id, $outside->id]], $viewer->token)->assertOk()->data();

        $this->assertSame(['count' => 1], $data);
        $this->assertNotNull(Db::table('admins')->where('id', $inside->id)->value('deleted_at'));
        $this->assertNull(Db::table('admins')->where('id', $outside->id)->value('deleted_at'));
    }
}
