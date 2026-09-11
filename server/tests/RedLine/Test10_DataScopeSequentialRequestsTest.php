<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\datascope\DataScope;
use support\Db;
use tests\Support\ApiTestCase;

/** 红线：同一 worker 连续处理不同管理员的请求，数据范围不串（快照只存在本请求的 Context 里）。 */
final class Test10_DataScopeSequentialRequestsTest extends ApiTestCase
{
    public function test_consecutive_requests_from_different_admins_do_not_share_scope(): void
    {
        $deptA = $this->createDepartment();
        $target = $this->actingAsAdmin([], ['department_id' => $this->createDepartment()]);
        $wide = $this->actingAsAdmin(['system.admin.list'], ['department_id' => $deptA], ['data_scope' => DataScope::ALL]);
        $narrow = $this->actingAsAdmin(['system.admin.list'], ['department_id' => $deptA], ['data_scope' => DataScope::DEPT]);
        $path = "/adminapi/system/admin/{$target->id}";

        $this->get($path, [], $wide->token)->assertOk();
        $this->get($path, [], $narrow->token)->assertCode(404);
        $this->get($path, [], $wide->token)->assertOk();
        $this->get($path, [], $narrow->token)->assertCode(404);
    }

    public function test_role_scope_change_applies_to_the_next_request(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin([], ['department_id' => $this->createDepartment()]);
        $viewer = $this->actingAsAdmin(['system.admin.list'], ['department_id' => $this->createDepartment()], ['data_scope' => DataScope::DEPT]);
        $roleId = (int) Db::table('admin_roles')->where('admin_id', $viewer->id)->value('role_id');
        $path = "/adminapi/system/admin/{$target->id}";

        $this->get($path, [], $viewer->token)->assertCode(404);
        $this->put("/adminapi/system/role/{$roleId}", ['data_scope' => DataScope::ALL], $super->token)->assertOk();
        $this->get($path, [], $viewer->token)->assertOk();
    }
}
