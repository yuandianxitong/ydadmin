<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\datascope\DataScope;
use tests\Support\ApiTestCase;

/** 红线：分页总数按范围统计，不泄露范围外记录的数量（spec §5.3）。 */
final class Test9_DataScopePaginationTest extends ApiTestCase
{
    public function test_total_only_counts_rows_in_scope(): void
    {
        $dept = $this->createDepartment();
        $viewer = $this->actingAsAdmin(['system.admin.list'], ['department_id' => $dept, 'nickname' => 'T9探针'], ['data_scope' => DataScope::DEPT]);
        $this->actingAsAdmin([], ['department_id' => $dept, 'nickname' => 'T9探针']);
        $this->actingAsAdmin([], ['nickname' => 'T9探针']);
        $this->actingAsAdmin([], ['department_id' => $this->createDepartment(), 'nickname' => 'T9探针']);

        $page1 = $this->get('/adminapi/system/admin', ['keyword' => 'T9探针', 'limit' => 1], $viewer->token)->assertOk()->data();
        $this->assertSame(2, $page1['pagination']['total']);
        $this->assertSame(2, $page1['pagination']['last_page']);

        $page3 = $this->get('/adminapi/system/admin', ['keyword' => 'T9探针', 'limit' => 1, 'page' => 3], $viewer->token)->assertOk()->data();
        $this->assertSame([], $page3['list']);
    }
}
