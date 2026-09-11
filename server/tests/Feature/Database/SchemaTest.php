<?php

declare(strict_types=1);

namespace tests\Feature\Database;

use support\Db;
use tests\TestCase;

final class SchemaTest extends TestCase
{
    public function test_m1a_tables_exist_and_legacy_permission_tables_do_not(): void
    {
        foreach (['admins', 'roles', 'admin_roles', 'menus', 'role_menus', 'departments', 'role_departments', 'system_configs', 'admin_login_logs'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), "缺少表 {$table}");
        }
        $this->assertFalse(Db::schema()->hasTable('permissions'));
        $this->assertFalse(Db::schema()->hasTable('role_permissions'));
    }

    public function test_tenant_and_legacy_columns_are_gone(): void
    {
        foreach (['admins', 'roles', 'admin_roles', 'menus', 'role_menus', 'departments', 'system_configs', 'admin_login_logs'] as $table) {
            $this->assertFalse(Db::schema()->hasColumn($table, 'tenant_id'), "{$table} 不应有 tenant_id");
        }
        $this->assertFalse(Db::schema()->hasColumn('admins', 'department'), '总 spec §6：删除 admins.department');
        $this->assertFalse(Db::schema()->hasColumn('menus', 'code'), 'menus.code 是 Saas 插件遗留');
        $this->assertTrue(Db::schema()->hasColumn('admins', 'department_id'));
    }

    public function test_menu_seeds_keep_tp8_ids_without_the_permission_page(): void
    {
        // 测试夹具新建的菜单 id 都大于 53，这里只看种子区间
        $ids = array_map('intval', Db::table('menus')->where('id', '<=', 53)->orderBy('id')->pluck('id')->all());
        $this->assertSame([1, 2, 10, 11, 12, 13, 14, 20, 21, 22, 23, 24, 25, 30, 31, 32, 33, 50, 51, 52, 53], $ids);
        $this->assertSame('system.role.permission', Db::table('menus')->where('id', 24)->value('permission'));
        $this->assertSame('/system/admin/index', Db::table('menus')->where('id', 10)->value('component'));
    }

    public function test_config_menu_seeds_keep_tp8_ids(): void
    {
        $menus = Db::table('menus')->whereIn('id', [100, 101])->orderBy('id')->get()->all();
        $this->assertCount(2, $menus);
        $this->assertSame([2, 100], [(int) $menus[0]->parent_id, (int) $menus[1]->parent_id]);
        $this->assertSame(['system.config.list', 'system.config.update'], [$menus[0]->permission, $menus[1]->permission]);
        $this->assertSame('/system/config/index', $menus[0]->component);
    }

    public function test_role_department_and_config_seeds(): void
    {
        $role = Db::table('roles')->where('id', 1)->first();
        $this->assertSame('super_admin', $role->name);
        $this->assertSame(1, (int) $role->is_system);
        $this->assertSame(1, (int) $role->data_scope);

        $codes = Db::table('departments')->whereBetween('id', [1, 6])->orderBy('id')->pluck('code')->all();
        $this->assertSame(['HQ', 'TECH', 'MARKET', 'FINANCE', 'TECH-FE', 'TECH-BE'], $codes);

        $basic = Db::table('system_configs')->where('config_group', 'basic')->pluck('config_value', 'config_key')->all();
        $this->assertCount(18, $basic);
        $this->assertSame('1', $basic['login_captcha']);
        $this->assertSame('5', $basic['login_max_retry']);
        $this->assertSame('30', $basic['login_lock_duration']);
        $this->assertSame('6', $basic['password_min_length']);
        $this->assertSame('/storage/uploads/images/favicon.ico', $basic['site_favicon']);
        $this->assertTrue(Db::schema()->hasColumn('system_configs', 'is_public'));
        $this->assertSame(18, Db::table('system_configs')->where('config_group', 'basic')->where('is_public', 1)->count(), 'basic 18 项都是前端公开配置');
    }

    public function test_init_sql_contains_no_admin_account(): void
    {
        $init = (string) file_get_contents(base_path() . '/database/install/init.sql');
        $this->assertStringNotContainsString('INSERT INTO `admins`', $init, '管理员账号由 php webman admin:init 建立');
    }
}
