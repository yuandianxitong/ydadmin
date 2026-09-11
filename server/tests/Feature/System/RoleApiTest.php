<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

final class RoleApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/role';

    /** @param array<string, mixed> $overrides */
    private function createRole(TestAdmin $actor, array $overrides = []): int
    {
        $suffix = bin2hex(random_bytes(3));
        $id = (int) $this->post(self::BASE, array_merge(['name' => "role_{$suffix}", 'title' => "接口角色{$suffix}"], $overrides), $actor->token)->assertOk()->data()['id'];
        $this->track('roles', $id);

        return $id;
    }

    private function roleOf(TestAdmin $admin): int
    {
        return (int) Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
    }

    public function test_index_rows_include_counts_and_dept_ids(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->createRole($super, ['data_scope' => 5, 'dept_ids' => [2, 3]]);
        $name = (string) Db::table('roles')->where('id', $roleId)->value('name');

        $row = $this->get(self::BASE, ['keyword' => $name], $super->token)->assertOk()->data()['list'][0];

        $this->assertSame($roleId, $row['id']);
        $this->assertSame([2, 3], $row['dept_ids']);
        foreach (['admins_count', 'menus_count', 'data_scope_text', 'is_system'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
    }

    public function test_show_returns_menu_authorisation_only(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->createRole($super, ['menu_ids' => [10, 11]]);

        $data = $this->get(self::BASE . "/{$roleId}", [], $super->token)->assertOk()->data();

        $this->assertSame(['menu_ids', 'menus'], array_keys($data), '契约：show 返回角色授权，不是角色基本信息');
        $this->assertEqualsCanonicalizing([10, 11], $data['menu_ids']);
        $this->assertSame($data, $this->get(self::BASE . "/{$roleId}/permissions", [], $super->token)->assertOk()->data());
    }

    public function test_custom_scope_departments_only_kept_for_data_scope_5(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->createRole($super, ['data_scope' => 2, 'dept_ids' => [2]]);
        $this->assertSame(0, Db::table('role_departments')->where('role_id', $roleId)->count());

        $this->put(self::BASE . "/{$roleId}", ['data_scope' => 5, 'dept_ids' => [3, 4]], $super->token)->assertOk();
        $this->assertEqualsCanonicalizing([3, 4], array_map('intval', Db::table('role_departments')->where('role_id', $roleId)->pluck('department_id')->all()));

        $this->put(self::BASE . "/{$roleId}", ['data_scope' => 1], $super->token)->assertOk();
        $this->assertSame(0, Db::table('role_departments')->where('role_id', $roleId)->count());

        $this->assertSame(lang('business.dept_not_found'), $this->put(self::BASE . "/{$roleId}", ['data_scope' => 5, 'dept_ids' => [999999]], $super->token)->assertCode(400)->message());
    }

    public function test_store_validates_uniqueness_and_references(): void
    {
        $super = $this->actingAsAdmin('super');

        $this->assertSame(lang('business.role_code_exists'), $this->post(self::BASE, ['name' => 'super_admin', 'title' => '重复'], $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.menu_not_found'), $this->post(self::BASE, ['name' => 'r_' . bin2hex(random_bytes(3)), 'title' => '坏菜单', 'menu_ids' => [999999]], $super->token)->assertCode(400)->message());
        $this->post(self::BASE, ['name' => '中文标识', 'title' => '非法'], $super->token)->assertCode(422);
    }

    public function test_update_ignores_menu_ids_and_protects_the_system_role(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->createRole($super, ['menu_ids' => [10]]);

        $this->put(self::BASE . "/{$roleId}", ['title' => '改名', 'menu_ids' => [20]], $super->token)->assertOk();
        $this->assertSame([10], array_map('intval', Db::table('role_menus')->where('role_id', $roleId)->pluck('menu_id')->all()), 'update 不改授权，授权走 assign-permissions');
        $this->assertSame(lang('business.system_role_no_modify'), $this->put(self::BASE . '/1', ['name' => 'renamed'], $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.system_role_no_status'), $this->put(self::BASE . '/1', ['status' => 0], $super->token)->assertCode(400)->message());
    }

    public function test_update_rejects_blank_name_title_or_status(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->createRole($super);
        $before = (array) Db::table('roles')->where('id', $roleId)->first();

        foreach (['name' => '', 'title' => '', 'status' => ''] as $field => $value) {
            $errors = $this->put(self::BASE . "/{$roleId}", [$field => $value], $super->token)->assertCode(422)->data()['errors'];
            $this->assertArrayHasKey($field, $errors, "字段 {$field} 提交空字符串应校验失败");
        }

        $after = (array) Db::table('roles')->where('id', $roleId)->first();
        $this->assertEquals($before, $after, '校验失败的更新不应改动角色行');
    }

    public function test_blank_data_scope_or_sort_is_rejected(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->createRole($super);
        $before = (array) Db::table('roles')->where('id', $roleId)->first();

        foreach (['data_scope' => '', 'sort' => ''] as $field => $value) {
            $errors = $this->put(self::BASE . "/{$roleId}", [$field => $value], $super->token)->assertCode(422)->data()['errors'];
            $this->assertArrayHasKey($field, $errors, "字段 {$field} 提交空字符串应校验失败");
        }
        $after = (array) Db::table('roles')->where('id', $roleId)->first();
        $this->assertEquals($before, $after, '校验失败的更新不应改动角色行');

        $errors = $this->post(self::BASE, ['name' => 'r_' . bin2hex(random_bytes(3)), 'title' => '空数据范围', 'data_scope' => ''], $super->token)->assertCode(422)->data()['errors'];
        $this->assertArrayHasKey('data_scope', $errors);
    }

    public function test_assign_permissions_validates_menu_ids(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->createRole($super, ['menu_ids' => [10]]);

        $this->put(self::BASE . "/{$roleId}/assign-permissions", ['menu_ids' => '11'], $super->token)->assertCode(422);
        $this->assertSame([10], array_map('intval', Db::table('role_menus')->where('role_id', $roleId)->pluck('menu_id')->all()));

        $this->put(self::BASE . "/{$roleId}/assign-permissions", [], $super->token)->assertCode(422);
        $this->assertSame([10], array_map('intval', Db::table('role_menus')->where('role_id', $roleId)->pluck('menu_id')->all()));

        $this->put(self::BASE . "/{$roleId}/assign-permissions", ['menu_ids' => []], $super->token)->assertOk();
        $this->assertSame(0, Db::table('role_menus')->where('role_id', $roleId)->count());
    }

    public function test_assign_permissions_takes_effect_on_the_next_request(): void
    {
        $super = $this->actingAsAdmin('super');
        $member = $this->actingAsAdmin(['system.admin.list']);
        $roleId = $this->roleOf($member);

        $this->get('/adminapi/system/role', [], $member->token)->assertCode(403);
        // 只认 menu_ids，permission_ids 被忽略（契约 §2.3）
        $this->put(self::BASE . "/{$roleId}/assign-permissions", ['menu_ids' => [20], 'permission_ids' => [1]], $super->token)->assertOk();
        $this->get('/adminapi/system/role', [], $member->token)->assertOk();
        $this->get('/adminapi/system/admin', [], $member->token)->assertCode(403); // 全量覆盖

        $response = $this->put(self::BASE . '/1/assign-permissions', ['menu_ids' => [10]], $super->token);
        $this->assertSame(200, $response->status());
        $response->assertCode(403); // 系统角色权限不可修改
    }

    public function test_status_change_takes_effect_on_the_next_request(): void
    {
        $super = $this->actingAsAdmin('super');
        $member = $this->actingAsAdmin(['system.admin.list']);

        // 先证明成员当前能访问（预热权限缓存），status 变更之后必须让它下一请求就失效
        $this->get('/adminapi/system/admin', [], $member->token)->assertOk();
        $this->put(self::BASE . "/{$this->roleOf($member)}/status", ['status' => 0], $super->token)->assertOk();
        $this->get('/adminapi/system/admin', [], $member->token)->assertCode(403);
        $this->put(self::BASE . '/1/status', ['status' => 0], $super->token)->assertCode(400);
    }

    public function test_delete_protections_and_batch_delete(): void
    {
        $super = $this->actingAsAdmin('super');
        $member = $this->actingAsAdmin();

        $this->assertSame(lang('business.system_role_no_delete'), $this->delete(self::BASE . '/1', [], $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.role_has_admins'), $this->delete(self::BASE . "/{$this->roleOf($member)}", [], $super->token)->assertCode(400)->message());
        $this->delete(self::BASE . '/' . $this->createRole($super), [], $super->token)->assertOk();

        $a = $this->createRole($super);
        $b = $this->createRole($super);
        $this->post(self::BASE . '/batch-delete', ['ids' => [$a, $b]], $super->token)->assertOk();
        $this->assertSame(2, Db::table('roles')->whereIn('id', [$a, $b])->whereNotNull('deleted_at')->count());
        $this->assertSame(lang('business.please_select_role'), $this->post(self::BASE . '/batch-delete', ['ids' => []], $super->token)->assertCode(400)->message());
    }

    public function test_options_and_trees(): void
    {
        $admin = $this->actingAsAdmin();

        $this->assertSame(['id', 'name', 'title'], array_keys($this->get(self::BASE . '/options', [], $admin->token)->assertOk()->data()[0]));
        $tree = $this->get(self::BASE . '/menu/tree', [], $admin->token)->assertOk()->data();
        $this->assertSame($tree, $this->get(self::BASE . '/permission/tree', [], $admin->token)->assertOk()->data());
        $this->assertContains(2, array_column($tree, 'id'));
    }
}
