<?php

declare(strict_types=1);

namespace tests\Feature\System;

use core\datascope\DataScope;
use support\Db;
use tests\Support\ApiTestCase;

final class DepartmentApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/department';

    /** @param array<int, array<string, mixed>> $nodes @return list<int> */
    private function flatten(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            $ids[] = (int) $node['id'];
            $ids = [...$ids, ...$this->flatten($node['children'] ?? [])];
        }

        return $ids;
    }

    /** @param array<string, mixed> $data */
    private function createViaApi(string $token, array $data): int
    {
        $id = (int) $this->post(self::BASE, $data, $token)->assertOk()->data()['id'];
        $this->track('departments', $id);

        return $id;
    }

    public function test_index_returns_tree_and_filters(): void
    {
        $admin = $this->actingAsAdmin(['system.department.list']);

        $tree = $this->get(self::BASE, [], $admin->token)->assertOk()->data();
        $hq = array_values(array_filter($tree, static fn (array $node): bool => $node['id'] === 1))[0];
        $this->assertSame('总公司', $hq['name']);
        $this->assertEqualsCanonicalizing([2, 3, 4], array_column($hq['children'], 'id'));

        $filtered = $this->get(self::BASE, ['keyword' => 'TECH-FE'], $admin->token)->assertOk()->data();
        $this->assertSame([5], array_column($filtered, 'id'), '父级被过滤掉时节点提升为根');
    }

    public function test_options_nodes_have_contract_fields_and_skip_disabled(): void
    {
        $admin = $this->actingAsAdmin();
        $disabled = $this->createDepartment(['status' => 0]);

        $tree = $this->get(self::BASE . '/options', [], $admin->token)->assertOk()->data();
        $this->assertSame(['id', 'parent_id', 'name', 'code', 'children'], array_keys($tree[0]));
        $this->assertNotContains($disabled, $this->flatten($tree));
    }

    public function test_show(): void
    {
        $admin = $this->actingAsAdmin(['system.department.list']);

        $this->assertSame('TECH', $this->get(self::BASE . '/2', [], $admin->token)->assertOk()->data()['code']);
        $this->assertSame(lang('business.dept_not_found'), $this->get(self::BASE . '/999999', [], $admin->token)->assertCode(400)->message());
    }

    public function test_store_and_update_validations(): void
    {
        $super = $this->actingAsAdmin('super');

        $this->assertSame(lang('business.dept_code_exists'), $this->post(self::BASE, ['parent_id' => 0, 'name' => '重复编码', 'code' => 'TECH'], $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.parent_dept_not_found'), $this->post(self::BASE, ['parent_id' => 999999, 'name' => '孤儿'], $super->token)->assertCode(400)->message());
        $this->post(self::BASE, ['name' => '缺上级'], $super->token)->assertCode(422);

        $parent = $this->createViaApi($super->token, ['parent_id' => 0, 'name' => '接口父部门']);
        $child = $this->createViaApi($super->token, ['parent_id' => $parent, 'name' => '接口子部门']);
        $this->assertSame(lang('business.dept_parent_not_self'), $this->put(self::BASE . "/{$parent}", ['parent_id' => $parent, 'name' => '接口父部门'], $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.dept_parent_not_child'), $this->put(self::BASE . "/{$parent}", ['parent_id' => $child, 'name' => '接口父部门'], $super->token)->assertCode(400)->message());
    }

    public function test_blank_parent_status_or_sort_is_rejected(): void
    {
        $super = $this->actingAsAdmin('super');
        $id = $this->createDepartment(['parent_id' => 0, 'status' => 1, 'sort' => 5]);

        foreach (['parent_id', 'status', 'sort'] as $field) {
            $response = $this->put(self::BASE . "/{$id}", ['name' => '接口部门', $field => ''], $super->token);
            $response->assertCode(422);
            $this->assertArrayHasKey($field, $response->data()['errors']);
        }

        $row = Db::table('departments')->where('id', $id)->first();
        $this->assertSame(0, (int) $row->parent_id);
        $this->assertSame(1, (int) $row->status);
        $this->assertSame(5, (int) $row->sort);
    }

    public function test_delete_protections_and_status(): void
    {
        $super = $this->actingAsAdmin('super');
        $withAdmin = $this->createDepartment();
        $this->actingAsAdmin([], ['department_id' => $withAdmin]);

        $this->assertSame(lang('business.dept_has_children'), $this->delete(self::BASE . '/1', [], $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.dept_has_admins'), $this->delete(self::BASE . "/{$withAdmin}", [], $super->token)->assertCode(400)->message());

        $empty = $this->createDepartment();
        $this->put(self::BASE . "/{$empty}/status", ['status' => 0], $super->token)->assertOk();
        $this->assertSame(0, (int) Db::table('departments')->where('id', $empty)->value('status'));
        $this->delete(self::BASE . "/{$empty}", [], $super->token)->assertOk();
    }

    public function test_moving_a_department_refreshes_data_scope(): void
    {
        $super = $this->actingAsAdmin('super');
        $x = $this->createDepartment(['name' => '范围X']);
        $y = $this->createDepartment(['name' => '范围Y']);
        $target = $this->actingAsAdmin([], ['department_id' => $y]);
        $viewer = $this->actingAsAdmin(['system.admin.list'], ['department_id' => $x], ['data_scope' => DataScope::DEPT_AND_CHILDREN]);

        $this->get("/adminapi/system/admin/{$target->id}", [], $viewer->token)->assertCode(404);
        $this->put(self::BASE . "/{$y}", ['parent_id' => $x, 'name' => '范围Y'], $super->token)->assertOk();
        $this->get("/adminapi/system/admin/{$target->id}", [], $viewer->token)->assertOk();
    }
}
