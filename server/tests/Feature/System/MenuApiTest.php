<?php

declare(strict_types=1);

namespace tests\Feature\System;

use core\auth\Permission;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

final class MenuApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/menu';

    /** @param array<string, mixed> $data */
    private function createMenu(TestAdmin $actor, array $data): int
    {
        $id = (int) $this->post(self::BASE, $data, $actor->token)->assertOk()->data()['id'];
        $this->track('menus', $id);

        return $id;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function pageMenu(array $overrides = []): array
    {
        $s = bin2hex(random_bytes(3));

        return array_merge(['parent_id' => 2, 'type' => 2, 'title' => "测试菜单{$s}", 'name' => "TestMenu{$s}", 'path' => "/test/{$s}", 'component' => "/test/{$s}/index"], $overrides);
    }

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

    public function test_index_returns_all_menus_unless_only_enabled(): void
    {
        $super = $this->actingAsAdmin('super');
        $disabled = $this->createMenu($super, $this->pageMenu(['status' => 0]));

        $all = $this->flatten($this->get(self::BASE, [], $super->token)->assertOk()->data());
        $this->assertContains($disabled, $all, '契约：默认含禁用菜单');
        $this->assertContains(11, $all, '含按钮');
        $this->assertContains($disabled, $this->flatten($this->get(self::BASE, ['only_enabled' => 'false'], $super->token)->assertOk()->data()));
        $this->assertNotContains($disabled, $this->flatten($this->get(self::BASE, ['only_enabled' => 1], $super->token)->assertOk()->data()));
    }

    public function test_options_tree_has_virtual_root(): void
    {
        $admin = $this->actingAsAdmin();
        $tree = $this->get(self::BASE . '/options', ['exclude_id' => 50], $admin->token)->assertOk()->data();

        $this->assertSame(0, $tree[0]['id']);
        $this->assertSame('根目录', $tree[0]['title']);
        $this->assertNotContains(50, $this->flatten($tree));
    }

    public function test_store_enforces_type_specific_required_fields(): void
    {
        $super = $this->actingAsAdmin('super');

        $page = $this->post(self::BASE, ['parent_id' => 2, 'type' => 2, 'title' => '缺路径'], $super->token);
        $page->assertCode(422);
        $this->assertArrayHasKey('path', $page->data()['errors']);

        $button = $this->post(self::BASE, ['parent_id' => 10, 'type' => 3, 'title' => '缺权限点'], $super->token);
        $button->assertCode(422);
        $this->assertArrayHasKey('permission', $button->data()['errors']);

        $this->createMenu($super, ['parent_id' => 10, 'type' => 3, 'title' => '导出', 'permission' => 'system.admin.export_' . bin2hex(random_bytes(2))]);
    }

    public function test_store_rejects_duplicates_and_button_parent(): void
    {
        $super = $this->actingAsAdmin('super');

        $this->assertSame(lang('business.menu_name_exists'), $this->post(self::BASE, $this->pageMenu(['name' => 'SystemAdmin']), $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.route_path_exists'), $this->post(self::BASE, $this->pageMenu(['path' => '/system/admin']), $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.button_no_children'), $this->post(self::BASE, $this->pageMenu(['parent_id' => 11]), $super->token)->assertCode(400)->message());
    }

    public function test_update_rejects_cycles(): void
    {
        $super = $this->actingAsAdmin('super');
        $parent = $this->createMenu($super, $this->pageMenu(['type' => 1, 'component' => 'LAYOUT']));
        $child = $this->createMenu($super, $this->pageMenu(['parent_id' => $parent]));

        $this->assertSame(lang('business.parent_not_self'), $this->put(self::BASE . "/{$parent}", $this->pageMenu(['parent_id' => $parent]), $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.parent_not_child'), $this->put(self::BASE . "/{$parent}", $this->pageMenu(['parent_id' => $child]), $super->token)->assertCode(400)->message());
    }

    public function test_delete_protections(): void
    {
        $super = $this->actingAsAdmin('super');
        $member = $this->actingAsAdmin();
        $used = $this->createMenu($super, $this->pageMenu());
        Db::table('role_menus')->insert(['role_id' => (int) Db::table('admin_roles')->where('admin_id', $member->id)->value('role_id'), 'menu_id' => $used]);

        $this->assertSame(lang('business.menu_has_children'), $this->delete(self::BASE . '/10', [], $super->token)->assertCode(400)->message());
        $this->assertSame(lang('business.menu_used_by_role'), $this->delete(self::BASE . "/{$used}", [], $super->token)->assertCode(400)->message());
        $this->delete(self::BASE . '/' . $this->createMenu($super, $this->pageMenu()), [], $super->token)->assertOk();
    }

    public function test_status_change_affects_permissions_on_the_next_request(): void
    {
        $super = $this->actingAsAdmin('super');
        $member = $this->actingAsAdmin(['system.role.list']);
        $this->get('/adminapi/system/role', [], $member->token)->assertOk();

        try {
            $this->put(self::BASE . '/20/status', ['status' => 0], $super->token)->assertOk();
            $this->get('/adminapi/system/role', [], $member->token)->assertCode(403);
        } finally {
            Db::table('menus')->where('id', 20)->update(['status' => 1]);
            Container::get(Permission::class)->clearAllCache();
        }
    }

    public function test_batch_sort_renumbers_siblings(): void
    {
        $super = $this->actingAsAdmin('super');
        $original = Db::table('menus')->whereIn('id', [11, 12, 13, 14])->pluck('sort', 'id')->all();

        try {
            $this->post(self::BASE . '/batch-sort', ['items' => [
                ['id' => 14, 'parent_id' => 10, 'sort' => 1],
                ['id' => 11, 'parent_id' => 10, 'sort' => 2],
            ]], $super->token)->assertOk();
            $this->assertEquals([14 => 10, 11 => 20], array_map('intval', Db::table('menus')->whereIn('id', [11, 14])->pluck('sort', 'id')->all()));

            $mismatch = $this->post(self::BASE . '/batch-sort', ['items' => [['id' => 21, 'parent_id' => 10, 'sort' => 1]]], $super->token);
            $this->assertSame(lang('business.sort_parent_mismatch'), $mismatch->assertCode(400)->message());
        } finally {
            foreach ($original as $id => $sort) {
                Db::table('menus')->where('id', $id)->update(['sort' => $sort]);
            }
        }
    }

    public function test_batch_delete(): void
    {
        $super = $this->actingAsAdmin('super');
        $a = $this->createMenu($super, $this->pageMenu());
        $b = $this->createMenu($super, $this->pageMenu());

        $this->post(self::BASE . '/batch-delete', ['ids' => [$a, $b]], $super->token)->assertOk();
        $this->assertSame(2, Db::table('menus')->whereIn('id', [$a, $b])->whereNotNull('deleted_at')->count());
    }

    /**
     * 控制器审查发现的问题：Laravel 对 '' 跳过非隐式规则（nullable 会直接放行空字符串），
     * 整型/布尔字段若仍写 nullable，空字符串会绕过校验直接写库，MySQL 严格模式下 500。
     * parent_id/status/sort/is_hidden 等改用 sometimes|required 后，空字符串必须校验失败。
     */
    public function test_blank_integer_or_boolean_fields_are_rejected(): void
    {
        $super = $this->actingAsAdmin('super');
        $id = $this->createMenu($super, $this->pageMenu());
        $before = Db::table('menus')->where('id', $id)->first();

        foreach (['status', 'sort', 'is_hidden'] as $field) {
            $response = $this->put(self::BASE . "/{$id}", $this->pageMenu([$field => '']), $super->token);
            $response->assertCode(422);
            $this->assertArrayHasKey($field, $response->data()['errors'], "字段 {$field} 应校验失败");
        }

        $this->assertEquals($before, Db::table('menus')->where('id', $id)->first(), '校验失败的请求不应改动数据');
    }

    /** 菜单写操作仅超管（M1b 路径 C）：非超管即便持有菜单权限点，也不能改权限码、状态、排序，不能增删。 */
    public function test_menu_writes_are_super_only(): void
    {
        $super = $this->actingAsAdmin('super');
        $actor = $this->actingAsAdmin(['system.menu.list', 'system.menu.create', 'system.menu.update', 'system.menu.delete']);
        $button = $this->createMenu($super, ['parent_id' => 10, 'type' => 3, 'title' => '越权探针', 'permission' => 'system.admin.probe_' . bin2hex(random_bytes(2))]);
        $before = (array) Db::table('menus')->where('id', $button)->first();
        $denied = lang('auth.super_admin_only');
        $page = $this->pageMenu();

        $response = $this->post(self::BASE, $page, $actor->token);
        $this->track('menus', (int) ($response->data()['id'] ?? 0)); // 回归时误建的行也要清掉
        $this->assertSame($denied, $response->assertCode(400)->message());
        $this->assertSame(0, Db::table('menus')->where('name', $page['name'])->count());

        // 路径 C：把手里按钮的权限码改成别的权限码
        $this->assertSame($denied, $this->put(self::BASE . "/{$button}", ['parent_id' => 10, 'type' => 3, 'title' => '越权探针', 'permission' => 'system.role.update'], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->put(self::BASE . "/{$button}/status", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->post(self::BASE . '/batch-sort', ['items' => [['id' => $button, 'parent_id' => 10, 'sort' => 99]]], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->delete(self::BASE . "/{$button}", [], $actor->token)->assertCode(400)->message());
        $this->assertSame($denied, $this->post(self::BASE . '/batch-delete', ['ids' => [$button]], $actor->token)->assertCode(400)->message());
        $this->assertEquals($before, (array) Db::table('menus')->where('id', $button)->first());

        // 读接口照旧按权限点
        $this->get(self::BASE, [], $actor->token)->assertOk();
    }
}
