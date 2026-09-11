<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\AdminLoginLogRepository;
use app\repository\system\AdminRepository;
use app\repository\system\DepartmentRepository;
use app\repository\system\MenuRepository;
use app\repository\system\RoleRepository;
use app\repository\system\SystemConfigRepository;
use support\Db;
use tests\TestCase;

final class SystemRepositoriesTest extends TestCase
{
    /** @var array<string, list<int>> 本用例插入的行：表 → id */
    private array $rows = [];

    protected function tearDown(): void
    {
        $admins = $this->rows['admins'] ?? [];
        $roles = $this->rows['roles'] ?? [];
        if ($admins !== []) {
            Db::table('admin_roles')->whereIn('admin_id', $admins)->delete();
        }
        if ($roles !== []) {
            Db::table('admin_roles')->whereIn('role_id', $roles)->delete();
            Db::table('role_menus')->whereIn('role_id', $roles)->delete();
            Db::table('role_departments')->whereIn('role_id', $roles)->delete();
        }
        foreach ($this->rows as $table => $ids) {
            Db::table($table)->whereIn('id', $ids)->delete();
        }
        $this->rows = [];
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function admin(array $attributes = []): int
    {
        $suffix = bin2hex(random_bytes(4));
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('admins')->insertGetId(array_merge([
            'username'   => "repo_{$suffix}",
            'email'      => "repo_{$suffix}@test.local",
            'password'   => password_hash('secret12', PASSWORD_DEFAULT),
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->rows['admins'][] = $id;

        return $id;
    }

    /** @param array<string, mixed> $attributes */
    private function role(array $attributes = []): int
    {
        $suffix = bin2hex(random_bytes(4));
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('roles')->insertGetId(array_merge([
            'name'       => "repo_role_{$suffix}",
            'title'      => "仓储测试角色{$suffix}",
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->rows['roles'][] = $id;

        return $id;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return list<int>
     */
    private function flattenIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            $ids[] = (int) $node['id'];
            $ids = [...$ids, ...$this->flattenIds($node['children'] ?? [])];
        }

        return $ids;
    }

    public function test_admin_password_is_hidden_except_for_auth_lookups(): void
    {
        $id = $this->admin(['username' => 'repo_pw_probe']);
        $repo = new AdminRepository();

        $this->assertArrayNotHasKey('password', $repo->find($id));
        $this->assertArrayHasKey('password', $repo->findByUsername('repo_pw_probe'));
        $this->assertArrayHasKey('password', $repo->findWithPassword($id));
        $this->assertSame('正常', $repo->find($id)['status_text']);
    }

    public function test_uniqueness_checks_include_soft_deleted_rows(): void
    {
        // 唯一索引对软删行同样生效，查重漏掉软删行会让插入撞唯一键变成 500
        $id = $this->admin(['username' => 'repo_gone', 'email' => 'repo_gone@test.local', 'deleted_at' => date('Y-m-d H:i:s')]);
        $repo = new AdminRepository();

        $this->assertNull($repo->find($id));
        $this->assertTrue($repo->existsUsername('repo_gone'));
        $this->assertTrue($repo->existsEmail('repo_gone@test.local'));
        $this->assertFalse($repo->existsUsername('repo_gone', $id));
    }

    public function test_assign_roles_and_detail_with_permissions(): void
    {
        $adminId = $this->admin();
        $roleId = $this->role();
        Db::table('role_menus')->insert([['role_id' => $roleId, 'menu_id' => 10], ['role_id' => $roleId, 'menu_id' => 11]]);
        $repo = new AdminRepository();

        $repo->assignRoles($adminId, [$roleId, $roleId]);
        $detail = $repo->getDetailWithPermissions($adminId);
        $this->assertSame([$roleId], array_column($detail['roles'], 'id'));
        $this->assertEqualsCanonicalizing([10, 11], array_column($detail['roles'][0]['menus'], 'id'));

        $repo->assignRoles($adminId, []);
        $this->assertSame([], $repo->getDetailWithPermissions($adminId)['roles']);
    }

    public function test_role_departments_usage_and_list_stats(): void
    {
        $roleId = $this->role();
        $repo = new RoleRepository();

        $repo->assignDepartments($roleId, [2, 3, 3]);
        $this->assertSame([$roleId => [2, 3]], $repo->getDeptIdsByRoleIds([$roleId]));

        $this->assertFalse($repo->isUsedByAdmin($roleId));
        $adminId = $this->admin();
        (new AdminRepository())->assignRoles($adminId, [$roleId]);
        $this->assertTrue($repo->isUsedByAdmin($roleId));
        Db::table('admins')->where('id', $adminId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->assertFalse($repo->isUsedByAdmin($roleId), '已软删的管理员不再算「角色下有管理员」');

        $row = $repo->getListWithStats([['id', '=', $roleId]], 1, 10)['list'][0];
        $this->assertSame([2, 3], $row['dept_ids']);
        $this->assertArrayHasKey('admins_count', $row);
        $this->assertArrayHasKey('menus_count', $row);
        $this->assertSame('全部数据', $row['data_scope_text']);
    }

    public function test_role_ids_by_admin_and_menu_ids_by_roles(): void
    {
        $enabled = $this->role();
        $other = $this->role();
        $disabled = $this->role(['status' => 0]);
        $deleted = $this->role(['deleted_at' => date('Y-m-d H:i:s')]);
        $goneMenu = (int) Db::table('menus')->insertGetId([
            'parent_id'  => 2,
            'type'       => 3,
            'title'      => '已删按钮',
            'permission' => 'repo.gone.' . bin2hex(random_bytes(2)),
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->rows['menus'][] = $goneMenu;
        Db::table('role_menus')->insert([
            ['role_id' => $enabled, 'menu_id' => 11],
            ['role_id' => $enabled, 'menu_id' => 10],
            ['role_id' => $other, 'menu_id' => 11],
            ['role_id' => $other, 'menu_id' => 20],
            ['role_id' => $other, 'menu_id' => $goneMenu],
            ['role_id' => $disabled, 'menu_id' => 30],
            ['role_id' => $deleted, 'menu_id' => 50],
        ]);
        $repo = new RoleRepository();

        // 只算启用、未删除角色的菜单；已软删的菜单不计（与 getAdminInfo() 的 menu_ids 口径一致）
        $this->assertSame([10, 11, 20], $repo->getMenuIdsByRoleIds([$other, $enabled, $disabled, $deleted]));
        $this->assertSame([], $repo->getMenuIdsByRoleIds([]));

        $adminId = $this->admin();
        (new AdminRepository())->assignRoles($adminId, [$disabled, $enabled, $deleted]);
        $this->assertSame([$enabled, $disabled], $repo->getRoleIdsByAdminId($adminId), '含禁用角色、不含软删角色，升序');
    }

    public function test_enabled_role_options_only_expose_id_name_title(): void
    {
        $options = (new RoleRepository())->getAllEnabled();

        $this->assertSame(['id', 'name', 'title'], array_keys($options[0]));
    }

    public function test_frontend_routes_tree_excludes_buttons_and_merges_meta(): void
    {
        $repo = new MenuRepository();
        Db::table('menus')->where('id', 10)->update(['meta' => json_encode(['keepAlive' => true, 'title' => '管理员'])]);
        try {
            $routes = $repo->getFrontendRoutes([2, 10, 11]);
        } finally {
            Db::table('menus')->where('id', 10)->update(['meta' => null]);
        }

        $this->assertCount(1, $routes);
        $this->assertSame('/system', $routes[0]['path']);
        $this->assertSame(['id', 'parent_id', 'name', 'path', 'component', 'redirect', 'type', 'meta', 'children'], array_keys($routes[0]));
        $child = $routes[0]['children'][0];
        $this->assertSame(10, $child['id']);
        $this->assertSame('管理员', $child['meta']['title'], 'menus.meta 覆盖派生的 meta');
        $this->assertTrue($child['meta']['keepAlive']);
        $this->assertSame([], $child['children'], '按钮（type=3）不进路由树');
        $this->assertSame([], $repo->getFrontendRoutes([]));
    }

    public function test_menu_options_have_virtual_root_and_no_buttons(): void
    {
        $tree = (new MenuRepository())->getMenuOptions(50);

        $root = $tree[0];
        unset($root['children']);
        $this->assertSame(['id' => 0, 'parent_id' => -1, 'title' => '根目录', 'type' => 0], $root);
        $ids = $this->flattenIds($tree[0]['children']);
        $this->assertContains(2, $ids);
        $this->assertNotContains(50, $ids, 'exclude_id 本身不出现');
        $this->assertNotContains(11, $ids, '按钮不出现');
    }

    public function test_children_ids_and_batch_sort(): void
    {
        $repo = new MenuRepository();
        $this->assertEqualsCanonicalizing([10, 11, 12, 13, 14], $repo->getAllChildrenIds(10));

        $original = Db::table('menus')->whereIn('id', [11, 12])->pluck('sort', 'id')->all();
        try {
            $repo->batchUpdateSortCase([['id' => 11, 'sort' => 20], ['id' => 12, 'sort' => 10]]);
            $this->assertEquals([11 => 20, 12 => 10], array_map('intval', Db::table('menus')->whereIn('id', [11, 12])->pluck('sort', 'id')->all()));
        } finally {
            foreach ($original as $id => $sort) {
                Db::table('menus')->where('id', $id)->update(['sort' => $sort]);
            }
        }
    }

    public function test_department_child_ids_and_code_uniqueness(): void
    {
        $repo = new DepartmentRepository();

        $this->assertEqualsCanonicalizing([5, 6], $repo->getChildIds(2));
        $this->assertTrue($repo->existsCode('TECH'));
        $this->assertFalse($repo->existsCode('TECH', 2));
        $this->assertEqualsCanonicalizing([1, 2], $repo->existingIds([1, 2, 999999]));
    }

    public function test_system_config_values_are_type_converted_and_cached(): void
    {
        $repo = new SystemConfigRepository();
        $repo->forgetCache();

        $all = $repo->getAllConfigs();
        $this->assertTrue($all['login_captcha']);
        $this->assertSame(6, $all['password_min_length']);
        $this->assertSame('元点Admin', $all['site_name']);
        $this->assertSame(5, $repo->getConfigValue('login_max_retry'));
        $this->assertSame('fallback', $repo->getConfigValue('no_such_key', 'fallback'));

        Db::table('system_configs')->where('config_key', 'site_name')->update(['config_value' => '改名']);
        try {
            $this->assertSame('元点Admin', $repo->getConfigValue('site_name'), '命中缓存');
            $repo->forgetCache();
            $this->assertSame('改名', $repo->getConfigValue('site_name'));
        } finally {
            Db::table('system_configs')->where('config_key', 'site_name')->update(['config_value' => '元点Admin']);
            $repo->forgetCache();
        }
    }

    public function test_login_log_record_parses_user_agent(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        (new AdminLoginLogRepository())->record([
            'admin_id'      => 0,
            'username'      => 'repo_log_probe',
            'ip'            => '127.0.0.1',
            'user_agent'    => $ua,
            'login_result'  => false,
            'login_message' => '密码错误',
        ]);
        $row = Db::table('admin_login_logs')->where('username', 'repo_log_probe')->first();
        Db::table('admin_login_logs')->where('username', 'repo_log_probe')->delete();

        $this->assertSame('Chrome 126.0.0.0', $row->browser);
        $this->assertSame('Mac OS X', $row->os);
        $this->assertSame(0, (int) $row->login_result);
        $this->assertNotNull($row->login_time);
    }
}
