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
        // 测试夹具新建的菜单 id 都大于 53，这里只看种子区间。9 是 M5a 会员管理目录，4/5/6/15 是 M6a 渠道管理目录（均沿用 TP8 id）。
        $ids = array_map('intval', Db::table('menus')->where('id', '<=', 53)->orderBy('id')->pluck('id')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 14, 15, 20, 21, 22, 23, 24, 25, 30, 31, 32, 33, 50, 51, 52, 53], $ids);
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

    public function test_payment_config_seeds(): void
    {
        $rows = Db::table('system_configs')->where('config_group', 'payment')->orderBy('sort_order')->get()->all();

        $this->assertSame([
            'pay_alipay_enabled', 'pay_alipay_sandbox', 'pay_alipay_app_id', 'pay_alipay_private_key',
            'pay_alipay_public_key', 'pay_alipay_notify_url',
            'pay_wechat_enabled', 'pay_wechat_app_id', 'pay_wechat_mch_id', 'pay_wechat_api_v3_key',
            'pay_wechat_serial_no', 'pay_wechat_private_key_path', 'pay_wechat_public_key_id',
            'pay_wechat_public_key', 'pay_wechat_notify_url',
        ], array_map(static fn (object $row): string => (string) $row->config_key, $rows), 'M5b spec §6：15 项，顺序即管理端表单顺序');

        foreach ($rows as $row) {
            $key = (string) $row->config_key;
            $this->assertSame(0, (int) $row->is_public, "{$key} 不得出现在 config/global");
            $this->assertSame(1, (int) $row->status, $key);

            if (in_array($key, ['pay_alipay_enabled', 'pay_wechat_enabled'], true)) {
                $this->assertSame('boolean', (string) $row->config_type, $key);
                $this->assertSame('0', (string) $row->config_value, '渠道默认关闭');
                $this->assertNull($row->config_depends, '开关本身不依赖别的项');

                continue;
            }

            $channel = str_starts_with($key, 'pay_alipay_') ? 'alipay' : 'wechat';
            $this->assertSame(
                ['field' => "pay_{$channel}_enabled", 'value' => '1'],
                json_decode((string) $row->config_depends, true),
                "{$key} 要挂在本渠道开关下联动显示"
            );
            if ($key === 'pay_alipay_sandbox') {
                $this->assertSame('boolean', (string) $row->config_type);
                $this->assertSame('0', (string) $row->config_value);
            } else {
                $this->assertSame('string', (string) $row->config_type, $key);
                $this->assertSame('', (string) $row->config_value, "{$key} 种子不带任何凭据或地址");
            }
        }

        $this->assertSame(0, Db::table('system_configs')->whereIn('config_key', ['pay_wechat_api_key', 'pay_wechat_cert_path'])->count(), '1.x 未使用的两项不再种入');
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

    public function test_m5b_payment_cron_seeds(): void
    {
        $seed = Db::table('cron_jobs')->where('command', 'payment:close-expired')->first();
        $this->assertNotNull($seed, 'M5b 关单定时任务种子');
        $this->assertSame('支付订单超时关闭', $seed->name);
        $this->assertSame('*/5 * * * *', $seed->expression);
        $this->assertSame(1, (int) $seed->status);

        $reconcile = Db::table('cron_jobs')->where('command', 'payment:reconcile-refunds')->first();
        $this->assertNotNull($reconcile, 'M5b 退款对账定时任务种子');
        $this->assertSame('退款结果对账', $reconcile->name);
        $this->assertSame('*/10 * * * *', $reconcile->expression);
        $this->assertSame(1, (int) $reconcile->status);

        $this->assertSame(0, Db::table('cron_jobs')->where('command', 'like', 'payment:refund%')->count(), '退款命令永远不进定时任务');
    }

    public function test_m5a_user_menu_seeds(): void
    {
        $menus = Db::table('menus')->whereIn('id', [9, 900, 901, 902, 903, 904, 910, 920])->orderBy('id')->get()->all();
        $this->assertCount(8, $menus);
        $this->assertSame([0, 9, 900, 900, 900, 900, 9, 9], array_map(static fn (object $m): int => (int) $m->parent_id, $menus));
        $this->assertSame([1, 2, 3, 3, 3, 3, 2, 2], array_map(static fn (object $m): int => (int) $m->type, $menus));
        $this->assertSame(
            ['user', 'user.list', 'user.detail', 'user.adjust-balance', 'user.adjust-points', 'user.status', 'user.balance-logs', 'user.points-logs'],
            array_map(static fn (object $m): string => (string) $m->permission, $menus),
            '权限点逐字沿用 TP8'
        );
        $this->assertSame('LAYOUT', $menus[0]->component);
        $this->assertSame('/user', $menus[0]->path);
        $this->assertSame('/user/user', $menus[0]->redirect);
        $this->assertSame('i-svg:users', $menus[0]->icon);
        $this->assertSame('UserList', $menus[1]->name);
        $this->assertSame('/user/user/index', $menus[1]->component);
        $this->assertSame('/user/balance-log/index', $menus[6]->component);
        $this->assertSame('/user/points-log/index', $menus[7]->component);
    }

    public function test_m6a_wechat_config_seeds(): void
    {
        $expected = [
            'wechat_official' => [
                'wechat_official_name', 'wechat_official_original_id', 'wechat_official_qrcode',
                'wechat_official_app_id', 'wechat_official_app_secret',
                'wechat_official_token', 'wechat_official_aes_key', 'wechat_official_encrypt_type',
            ],
            'wechat_mini' => [
                'wechat_mini_name', 'wechat_mini_original_id', 'wechat_mini_qrcode',
                'wechat_mini_app_id', 'wechat_mini_app_secret',
                'wechat_mini_msg_token', 'wechat_mini_msg_aes_key', 'wechat_mini_msg_format', 'wechat_mini_encrypt_type',
            ],
            'wechat_open' => ['wechat_open_app_id', 'wechat_open_app_secret'],
        ];

        $total = 0;
        foreach ($expected as $group => $keys) {
            $rows = Db::table('system_configs')->where('config_group', $group)->orderBy('sort_order')->get()->all();
            $this->assertSame($keys, array_map(static fn (object $row): string => (string) $row->config_key, $rows), "{$group}：键与顺序逐字沿用 1.x（顺序即渠道配置页表单顺序）");
            $total += count($rows);

            foreach ($rows as $row) {
                $key = (string) $row->config_key;
                $this->assertSame(0, (int) $row->is_public, "{$key} 不得出现在 config/global");
                $this->assertSame(1, (int) $row->status, $key);
                $this->assertNull($row->config_depends, "{$key}：1.x 渠道配置不做联动显示");
            }
        }
        $this->assertSame(19, $total, 'spec §6.1：三组共 19 键');

        $types = Db::table('system_configs')->where('config_key', 'like', 'wechat\_%')->pluck('config_type', 'config_key')->all();
        $this->assertSame('file', $types['wechat_official_qrcode']);
        $this->assertSame('file', $types['wechat_mini_qrcode']);
        foreach (['wechat_official_encrypt_type', 'wechat_mini_encrypt_type', 'wechat_mini_msg_format'] as $selectKey) {
            $this->assertSame('select', $types[$selectKey], $selectKey);
        }

        $encrypt = Db::table('system_configs')->where('config_key', 'wechat_official_encrypt_type')->first();
        $this->assertSame('1', (string) $encrypt->config_value, '默认明文模式');
        // assertEquals（非 assertSame）：MySQL 的 JSON 列在读出时按（长度,字典序）归一化成员顺序，这里只关心键值内容。
        $this->assertEquals(['1' => '明文模式', '2' => '兼容模式', '3' => '安全模式'], json_decode((string) $encrypt->config_options, true));
        $format = Db::table('system_configs')->where('config_key', 'wechat_mini_msg_format')->first();
        $this->assertSame('JSON', (string) $format->config_value);
        $this->assertEquals(['JSON' => 'JSON', 'XML' => 'XML'], json_decode((string) $format->config_options, true));

        foreach (['wechat_official_app_id', 'wechat_official_app_secret', 'wechat_mini_app_id', 'wechat_mini_app_secret', 'wechat_open_app_id', 'wechat_open_app_secret'] as $credential) {
            $this->assertSame('', (string) Db::table('system_configs')->where('config_key', $credential)->value('config_value'), "{$credential} 种子不带凭据");
        }

        // 凭据类键名必须命中敏感黑名单（即使将来有人误把 is_public 改成 1，config/global 也不会吐出来）
        foreach (['wechat_official_app_secret', 'wechat_official_token', 'wechat_official_aes_key', 'wechat_mini_app_secret', 'wechat_mini_msg_token', 'wechat_mini_msg_aes_key', 'wechat_open_app_secret'] as $secretKey) {
            $this->assertTrue(\app\service\system\SystemConfigService::isSensitiveKey($secretKey), "{$secretKey} 应被敏感键黑名单覆盖");
        }
    }

    public function test_m6a_channel_menu_seeds(): void
    {
        $menus = Db::table('menus')->whereIn('id', [4, 5, 400, 6, 500, 15, 550])->orderBy('id')->get()->all();
        $this->assertCount(7, $menus);
        $byId = [];
        foreach ($menus as $menu) {
            $byId[(int) $menu->id] = $menu;
        }

        $this->assertSame(0, (int) $byId[4]->parent_id);
        $this->assertSame(1, (int) $byId[4]->type);
        $this->assertSame('渠道管理', $byId[4]->title);
        $this->assertSame('Channel', $byId[4]->name);
        $this->assertSame('/channel', $byId[4]->path);
        $this->assertSame('LAYOUT', $byId[4]->component);
        $this->assertSame('/channel/official/config', $byId[4]->redirect);
        $this->assertSame('channel', $byId[4]->permission);
        $this->assertSame(700, (int) $byId[4]->sort);

        $expected = [
            // id => [parent, type, name, path, component, permission]
            5   => [4, 1, 'ChannelOfficial', '/channel/official', 'LAYOUT', 'channel.official'],
            400 => [5, 2, 'ChannelOfficialConfig', '/channel/official/config', '/channel/official/config', 'channel.official.config'],
            6   => [4, 1, 'ChannelMiniApp', '/channel/miniapp', 'LAYOUT', 'channel.miniapp'],
            500 => [6, 2, 'ChannelMiniAppConfig', '/channel/miniapp/config', '/channel/miniapp/config', 'channel.miniapp.config'],
            15  => [4, 1, 'ChannelOpen', '/channel/open', 'LAYOUT', 'channel.open'],
            550 => [15, 2, 'ChannelOpenConfig', '/channel/open/config', '/channel/open/config', 'channel.open.config'],
        ];
        foreach ($expected as $id => [$parent, $type, $name, $path, $component, $permission]) {
            $menu = $byId[$id];
            $this->assertSame([$parent, $type, $name, $path, $component, $permission], [
                (int) $menu->parent_id, (int) $menu->type, $menu->name, $menu->path, $menu->component, $menu->permission,
            ], "菜单 {$id} 逐字沿用 1.x");
        }

        // 配置页组件必须真实存在（admin 前端与 1.x 逐字节一致）
        foreach (['official', 'miniapp', 'open'] as $channel) {
            $this->assertFileExists(base_path() . "/../admin/src/views/channel/{$channel}/config.vue");
        }

        // M6c 的按钮 401、菜单 410/420 及其按钮不在 M6a 种入
        $this->assertSame(0, Db::table('menus')->whereIn('id', [401, 410, 411, 412, 420, 421, 422, 423])->count());
    }

    public function test_init_sql_contains_no_admin_account(): void
    {
        $init = (string) file_get_contents(base_path() . '/database/install/init.sql');
        $this->assertStringNotContainsString('INSERT INTO `admins`', $init, '管理员账号由 php webman admin:init 建立');
    }

    public function test_m5a_asset_tables_exist_with_expected_columns_and_indexes(): void
    {
        foreach (['users', 'balance_logs', 'points_logs'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), "缺少表 {$table}");
        }

        foreach ([
            'nickname', 'avatar', 'mobile', 'email', 'password', 'gender', 'birthday',
            'openid', 'oa_openid', 'unionid', 'mini_openid',
            'last_login_ip', 'last_login_time', 'login_count', 'status', 'balance', 'points',
            'created_at', 'updated_at', 'deleted_at',
        ] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('users', $column), "users 缺列 {$column}");
        }

        foreach (['user_id', 'amount', 'before_balance', 'after_balance', 'type', 'source', 'remark', 'operator_id', 'created_at'] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('balance_logs', $column), "balance_logs 缺列 {$column}");
        }
        $this->assertFalse(Db::schema()->hasColumn('balance_logs', 'updated_at'), 'balance_logs 无 updated_at');
        $this->assertFalse(Db::schema()->hasColumn('balance_logs', 'deleted_at'), 'balance_logs 不软删');

        foreach (['user_id', 'points', 'before_points', 'after_points', 'type', 'source', 'remark', 'operator_id', 'created_at'] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('points_logs', $column), "points_logs 缺列 {$column}");
        }
        $this->assertFalse(Db::schema()->hasColumn('points_logs', 'updated_at'), 'points_logs 无 updated_at');
        $this->assertFalse(Db::schema()->hasColumn('points_logs', 'deleted_at'), 'points_logs 不软删');

        // uk_mobile：重复手机号必须被唯一索引拒绝
        $now = date('Y-m-d H:i:s');
        Db::table('users')->insert([
            'mobile' => '13800009999', 'status' => 1, 'balance' => '0.00', 'points' => 0,
            'login_count' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        try {
            Db::table('users')->insert([
                'mobile' => '13800009999', 'status' => 1, 'balance' => '0.00', 'points' => 0,
                'login_count' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->fail('uk_mobile 唯一索引未生效');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('uk_mobile', $e->getMessage());
        } finally {
            Db::table('users')->where('mobile', '13800009999')->delete();
        }
    }

    public function test_m5b_payment_tables_exist_with_expected_columns_and_indexes(): void
    {
        foreach (['payment_orders', 'refund_orders'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), "缺少表 {$table}");
        }

        foreach ([
            'id', 'user_id', 'biz_type', 'client_type', 'order_no', 'app_id', 'trade_no', 'channel', 'trade_type', 'subject',
            'amount_cents', 'refunded_cents', 'status', 'error_msg', 'expires_at', 'paid_at', 'closed_at',
            'notify_data', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('payment_orders', $column), "payment_orders 缺列 {$column}");
        }
        $this->assertFalse(Db::schema()->hasColumn('payment_orders', 'deleted_at'), 'payment_orders 不软删');

        foreach ([
            'id', 'refund_no', 'payment_order_id', 'amount_cents', 'reason', 'status', 'channel_refund_no',
            'error_msg', 'operator', 'refunded_at', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue(Db::schema()->hasColumn('refund_orders', $column), "refund_orders 缺列 {$column}");
        }
        $this->assertFalse(Db::schema()->hasColumn('refund_orders', 'deleted_at'), 'refund_orders 不软删');

        $indexes = static function (string $table): array {
            $result = [];
            foreach (Db::select("SHOW INDEX FROM `{$table}`") as $row) {
                $result[$row->Key_name][(int) $row->Seq_in_index] = $row->Column_name;
            }

            return array_map(static function (array $columns): array {
                ksort($columns);

                return array_values($columns);
            }, $result);
        };

        $this->assertSame([
            'PRIMARY'            => ['id'],
            'uk_order_no'        => ['order_no'],
            'idx_user_created'   => ['user_id', 'created_at'],
            'idx_status_expires' => ['status', 'expires_at'],
            'idx_trade_no'       => ['trade_no'],
        ], $indexes('payment_orders'));

        $this->assertSame([
            'PRIMARY'             => ['id'],
            'uk_refund_no'        => ['refund_no'],
            'idx_payment_order'   => ['payment_order_id'],
            'idx_status_created'  => ['status', 'created_at'],
        ], $indexes('refund_orders'));

        // uk_order_no：重复订单号必须抛 UniqueConstraintViolationException（Task 7 的单号重试靠它识别）
        $now = date('Y-m-d H:i:s');
        $orderNo = 'RSCHEMA' . bin2hex(random_bytes(6));
        $row = [
            'user_id' => 1, 'biz_type' => 'recharge', 'client_type' => 'pc', 'order_no' => $orderNo,
            'channel' => 'wechat', 'trade_type' => 'native', 'subject' => '余额充值', 'amount_cents' => 100,
            'expires_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ];
        Db::table('payment_orders')->insert($row);
        try {
            Db::table('payment_orders')->insert($row);
            $this->fail('uk_order_no 唯一索引未生效');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('uk_order_no', $e->getMessage());
        } finally {
            Db::table('payment_orders')->where('order_no', $orderNo)->delete();
        }

        // 默认值：refunded_cents=0、status=pending
        $this->assertSame(0, Db::table('payment_orders')->where('order_no', $orderNo)->count());
        Db::table('payment_orders')->insert($row);
        try {
            $saved = Db::table('payment_orders')->where('order_no', $orderNo)->first();
            $this->assertSame(0, (int) $saved->refunded_cents);
            $this->assertSame('pending', $saved->status);
        } finally {
            Db::table('payment_orders')->where('order_no', $orderNo)->delete();
        }
    }
}
