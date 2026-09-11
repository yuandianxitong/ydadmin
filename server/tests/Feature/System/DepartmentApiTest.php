<?php

declare(strict_types=1);

namespace tests\Feature\System;

use core\datascope\DataScope;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestResponse;

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

    /** @param array<string, mixed> $data 预期失败的新建：回归时误建的行也登记清理 */
    private function attemptCreate(string $token, array $data): TestResponse
    {
        $response = $this->post(self::BASE, $data, $token);
        $this->track('departments', (int) ($response->data()['id'] ?? 0));

        return $response;
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

    /** 部门树把 NULL 编码显示成 ''，编辑表单原样回传：空编码必须存成 NULL，否则第二个这样保存的部门撞 uk_code 报 500。 */
    public function test_blank_code_on_update_is_stored_as_null(): void
    {
        $super = $this->actingAsAdmin('super');
        $first = $this->createViaApi($super->token, ['parent_id' => 0, 'name' => '无编码一']);
        $second = $this->createViaApi($super->token, ['parent_id' => 0, 'name' => '无编码二']);

        $this->put(self::BASE . "/{$first}", ['name' => '无编码一', 'code' => ''], $super->token)->assertOk();
        $this->put(self::BASE . "/{$second}", ['name' => '无编码二', 'code' => ''], $super->token)->assertOk();

        $this->assertNull(Db::table('departments')->where('id', $first)->value('code'));
        $this->assertNull(Db::table('departments')->where('id', $second)->value('code'));

        $code = 'TRIM-' . strtoupper(bin2hex(random_bytes(3)));
        $this->put(self::BASE . "/{$first}", ['name' => '无编码一', 'code' => "  {$code}  "], $super->token)->assertOk();
        $this->assertSame($code, Db::table('departments')->where('id', $first)->value('code'));
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

    /** 路径 E：非超管的部门写操作限自己的数据范围——否则改挂 parent_id 就能把别的部门挂进自己的范围。 */
    public function test_scoped_admin_writes_departments_only_inside_own_scope(): void
    {
        $a = $this->createDepartment(['name' => '写范围A']);
        $a1 = $this->createDepartment(['name' => '写范围A1', 'parent_id' => $a]);
        $b = $this->createDepartment(['name' => '写范围B']);
        $actor = $this->actingAsAdmin(['system.department.create', 'system.department.update', 'system.department.delete'], ['department_id' => $a], ['data_scope' => DataScope::DEPT_AND_CHILDREN]);
        $denied = lang('business.dept_out_of_scope');

        // 新建：上级必须在范围内，根（0）也不行
        $rootName = '越权根部门' . bin2hex(random_bytes(2));
        $childName = '越权子部门' . bin2hex(random_bytes(2));
        $this->assertSame($denied, $this->attemptCreate($actor->token, ['parent_id' => 0, 'name' => $rootName])->assertCode(400)->message());
        $this->assertSame($denied, $this->attemptCreate($actor->token, ['parent_id' => $b, 'name' => $childName])->assertCode(400)->message());
        $this->assertSame(0, Db::table('departments')->whereIn('name', [$rootName, $childName])->count());
        $this->createViaApi($actor->token, ['parent_id' => $a1, 'name' => '范围内新部门']);

        // 改、改挂、改状态、删：目标必须在范围内
        $this->assertSame($denied, $this->put(self::BASE . "/{$b}", ['name' => '被改名'], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->put(self::BASE . "/{$b}", ['parent_id' => $a, 'name' => '写范围B'], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->put(self::BASE . "/{$b}/status", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->delete(self::BASE . "/{$b}", [], $actor->token)->assertCode(400)->message());
        $row = Db::table('departments')->where('id', $b)->first();
        $this->assertSame('写范围B', $row->name);
        $this->assertSame(0, (int) $row->parent_id, '路径 E：B 没有被挂进 A 的范围');
        $this->assertSame(1, (int) $row->status);
        $this->assertNull($row->deleted_at);

        // 改挂范围内的部门：新上级也必须在范围内；原样回传当前上级不算改挂
        $this->assertSame($denied, $this->put(self::BASE . "/{$a1}", ['parent_id' => 0, 'name' => '写范围A1'], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->put(self::BASE . "/{$a1}", ['parent_id' => $b, 'name' => '写范围A1'], $actor->token)->assertCode(400)->message());
        $this->assertSame($a, (int) Db::table('departments')->where('id', $a1)->value('parent_id'));
        $this->put(self::BASE . "/{$a1}", ['parent_id' => $a, 'name' => '写范围A1改'], $actor->token)->assertOk();
        $this->assertSame('写范围A1改', Db::table('departments')->where('id', $a1)->value('name'));
    }

    /** 数据范围为「全部」的非超管不受限；「仅本人」的范围里没有部门，任何部门写操作都拒绝。 */
    public function test_all_scope_admin_is_unrestricted_and_self_scope_writes_nothing(): void
    {
        $wide = $this->actingAsAdmin(['system.department.create'], [], ['data_scope' => DataScope::ALL]);
        $this->createViaApi($wide->token, ['parent_id' => 0, 'name' => '全部范围建根部门']);

        $own = $this->createDepartment();
        $self = $this->actingAsAdmin(['system.department.create', 'system.department.update'], ['department_id' => $own], ['data_scope' => DataScope::SELF]);
        $this->assertSame(lang('business.dept_out_of_scope'), $this->attemptCreate($self->token, ['parent_id' => $own, 'name' => '仅本人建部门'])->assertCode(400)->message());
        $this->assertSame(lang('business.dept_out_of_scope'), $this->put(self::BASE . "/{$own}", ['name' => '仅本人改部门'], $self->token)->assertCode(400)->message());
    }
}
