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
        $this->assertSame([1, 2, 3, 10, 11, 12, 13, 14, 20, 21, 22, 23, 24, 25, 30, 31, 32, 33, 50, 51, 52, 53], $ids);
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

    public function test_m1c_file_table_and_menu_seeds(): void
    {
        $this->assertTrue(Db::schema()->hasTable('files'));
        foreach (['name', 'path', 'url', 'mime_type', 'extension', 'size', 'group', 'upload_by', 'storage', 'deleted_at'] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('files', $column), "files 缺列 {$column}");
        }
        $this->assertFalse(Db::schema()->hasColumn('files', 'tenant_id'));
        $this->assertFalse(Db::schema()->hasTable('file_categories'), 'spec §3.1：分组是 files.group 字符串，不建分类表');

        $menus = Db::table('menus')->whereIn('id', [70, 71, 72])->orderBy('id')->get()->all();
        $this->assertCount(3, $menus);
        $this->assertSame([2, 70, 70], array_map(static fn (object $m): int => (int) $m->parent_id, $menus));
        $this->assertSame(
            ['system.file.list', 'system.file.delete', 'system.file.update'],
            array_map(static fn (object $m): string => (string) $m->permission, $menus)
        );
        $this->assertSame('/system/file/index', $menus[0]->component);
    }

    public function test_storage_config_seeds(): void
    {
        $rows = Db::table('system_configs')->where('config_group', 'storage')->orderBy('sort_order')->get()->all();
        $this->assertCount(19, $rows, 'TP8 的 18 项 + 本项目新增的 storage_oss_region');

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[(string) $row->config_key] = $row;
        }
        $this->assertSame('local', $byKey['storage_driver']->config_value);
        $this->assertSame('10', $byKey['storage_upload_max_size']->config_value);
        $this->assertSame('5', $byKey['storage_image_max_size']->config_value);
        $this->assertStringContainsString('svg', $byKey['storage_upload_allowed_ext']->config_value, '种子放行 svg，危险扩展名由 denylist 拦（红线 Test15）');
        // assertEquals（非 assertSame）：MySQL 的 JSON 列在读出时按（长度,字典序）归一化成员顺序，
        // 不保留写入顺序，四个驱动名长度不同会被重排；这里只关心键值内容，顺序不是契约的一部分。
        $this->assertEquals(
            ['local' => '本地存储', 'aliyun' => '阿里云OSS', 'tencent' => '腾讯云COS', 'qiniu' => '七牛云'],
            json_decode((string) $byKey['storage_driver']->config_options, true)
        );
        $this->assertSame(
            ['field' => 'storage_driver', 'value' => 'aliyun'],
            json_decode((string) $byKey['storage_oss_bucket']->config_depends, true),
            'config_depends 驱动前端的联动显示'
        );

        // storage_oss_region：契约 §2.9.3 键集之外、本项目显式新增的一项（默认空，留空时由驱动从 endpoint 推导）
        $this->assertArrayHasKey('storage_oss_region', $byKey);
        $this->assertSame('', $byKey['storage_oss_region']->config_value);
        $this->assertSame(15, (int) $byKey['storage_oss_region']->sort_order, '排在 storage_oss_domain(14) 之后，不打乱 TP8 的编号');
        $this->assertSame(
            ['field' => 'storage_driver', 'value' => 'aliyun'],
            json_decode((string) $byKey['storage_oss_region']->config_depends, true)
        );

        // is_public：前端要读的 7 个键才公开，凭据与 bucket/endpoint/region 一律不公开
        $public = ['storage_driver', 'storage_upload_max_size', 'storage_upload_allowed_ext', 'storage_image_max_size', 'storage_oss_domain', 'storage_cos_domain', 'storage_qiniu_domain'];
        foreach ($byKey as $key => $row) {
            $this->assertSame(in_array($key, $public, true) ? 1 : 0, (int) $row->is_public, "{$key} 的 is_public 不对");
            $this->assertSame(1, (int) $row->status, "{$key} 应当启用");
        }
    }

    public function test_m3_cron_tables_and_menu_seeds(): void
    {
        foreach (['cron_jobs', 'cron_job_logs'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), "缺少表 {$table}");
        }
        foreach (['name', 'command', 'expression', 'description', 'status', 'last_run_at', 'last_status', 'last_result', 'run_count', 'sort', 'created_by', 'deleted_at'] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('cron_jobs', $column), "cron_jobs 缺列 {$column}");
        }
        $this->assertFalse(Db::schema()->hasColumn('cron_jobs', 'cron_expression'), '列名沿用 TP8 的 expression，cron_expression 只是接口字段');
        foreach (['cron_job_id', 'trigger', 'status', 'output', 'error', 'started_at', 'finished_at', 'duration', 'created_at'] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('cron_job_logs', $column), "cron_job_logs 缺列 {$column}");
        }
        $this->assertFalse(Db::schema()->hasColumn('cron_job_logs', 'updated_at'));

        $menus = Db::table('menus')->whereBetween('id', [90, 95])->orderBy('id')->get()->all();
        $this->assertCount(6, $menus);
        $this->assertSame([2, 90, 90, 90, 90, 90], array_map(static fn (object $m): int => (int) $m->parent_id, $menus));
        $this->assertSame(
            ['system.cron_job.list', 'system.cron_job.create', 'system.cron_job.update', 'system.cron_job.delete', 'system.cron_job.run', 'system.cron_job.clear'],
            array_map(static fn (object $m): string => (string) $m->permission, $menus)
        );
        $this->assertSame('/system/cron-job/index', $menus[0]->component);
        $this->assertSame('/system/cron-job', $menus[0]->path);
        $this->assertSame('SystemCronJob', $menus[0]->name);
        $this->assertSame('i-svg:bolt', $menus[0]->icon);

        $seed = Db::table('cron_jobs')->where('command', 'log:archive --days=90')->first();
        $this->assertNotNull($seed, '种子示例任务');
        $this->assertSame('0 3 * * *', $seed->expression);
        $this->assertSame(1, (int) $seed->status);
    }

    public function test_m4_online_menu_seeds(): void
    {
        $menus = Db::table('menus')->whereIn('id', [120, 121])->orderBy('id')->get()->all();
        $this->assertCount(2, $menus);
        $this->assertSame([2, 120], array_map(static fn (object $m): int => (int) $m->parent_id, $menus));
        $this->assertSame(['system.online.list', 'system.online.logout'], array_map(static fn (object $m): string => (string) $m->permission, $menus));
        $this->assertSame('/system/online/index', $menus[0]->component);
        $this->assertSame('/system/online', $menus[0]->path);
        $this->assertSame('SystemOnline', $menus[0]->name);
        $this->assertSame('i-svg:users-round', $menus[0]->icon);
        $this->assertSame(12, (int) $menus[0]->sort);
        $this->assertSame(3, (int) $menus[1]->type);
    }

    public function test_init_sql_contains_no_admin_account(): void
    {
        $init = (string) file_get_contents(base_path() . '/database/install/init.sql');
        $this->assertStringNotContainsString('INSERT INTO `admins`', $init, '管理员账号由 php webman admin:init 建立');
    }
}
