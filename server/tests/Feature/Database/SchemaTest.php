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
        // 测试夹具新建的菜单 id 都大于 53，这里只看种子区间。9 是 M5a 会员管理目录，4/5/6/15 是 M6a 渠道管理目录，7 是 M7a 内容管理目录，8 是 M7b 应用管理目录，16 是 M7c 装修目录（均沿用 TP8 id）。
        $ids = array_map('intval', Db::table('menus')->where('id', '<=', 53)->orderBy('id')->pluck('id')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 20, 21, 22, 23, 24, 25, 30, 31, 32, 33, 50, 51, 52, 53], $ids);
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
        $importMenu = Db::table('menus')->where('id', 905)->first();
        $this->assertNotNull($importMenu);
        $this->assertSame('user.import', $importMenu->permission);
        $this->assertSame(900, (int) $importMenu->parent_id);
        $payment = Db::table('menus')->whereIn('id', [930, 931, 932])->orderBy('id')->get()->all();
        $this->assertCount(3, $payment);
        $this->assertSame(['payment.order.list', 'payment.order.detail', 'payment.order.refund'], array_map(
            static fn (object $m): string => (string) $m->permission,
            $payment
        ));
        $this->assertSame('/user/payment-order/index', $payment[0]->component);
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

        // M6c 的按钮 401 不在 M6a 种入（410–423 见 test_m6c_official_ops_menu_seeds）
        $this->assertSame(0, Db::table('menus')->whereIn('id', [401])->count());
    }

    public function test_m6c_official_ops_menu_seeds(): void
    {
        $menus = Db::table('menus')->whereIn('id', [410, 411, 412, 420, 421, 422, 423])->orderBy('id')->get()->all();
        $this->assertCount(7, $menus);

        $expected = [
            // id => [parent, type, title, name, path, component, redirect, icon, permission, sort]
            410 => [5, 2, '自定义菜单', 'ChannelOfficialMenu', '/channel/official/menu', '/channel/official/menu', null, 'el-icon-Grid', 'channel.official.menu', 2],
            411 => [410, 3, '创建', null, null, null, null, null, 'channel.official.menu.create', 1],
            412 => [410, 3, '删除', null, null, null, null, null, 'channel.official.menu.delete', 2],
            420 => [5, 2, '自动回复', 'ChannelAutoReply', '/channel/official/auto-reply', '/channel/official/auto-reply', null, 'el-icon-ChatSquare', 'channel.official.auto_reply', 3],
            421 => [420, 3, '新增', null, null, null, null, null, 'channel.official.auto_reply.create', 1],
            422 => [420, 3, '编辑', null, null, null, null, null, 'channel.official.auto_reply.update', 2],
            423 => [420, 3, '删除', null, null, null, null, null, 'channel.official.auto_reply.delete', 3],
        ];
        foreach ($menus as $menu) {
            $id = (int) $menu->id;
            $this->assertSame($expected[$id], [
                (int) $menu->parent_id, (int) $menu->type, $menu->title, $menu->name, $menu->path,
                $menu->component, $menu->redirect, $menu->icon, $menu->permission, (int) $menu->sort,
            ], "菜单 {$id}");
            $this->assertSame(0, (int) $menu->is_hidden, "菜单 {$id} is_hidden");
            $this->assertSame(1, (int) $menu->is_cache, "菜单 {$id} is_cache");
            $this->assertSame(1, (int) $menu->status, "菜单 {$id} status");
            $this->assertSame(1, (int) $menu->breadcrumb, "菜单 {$id} breadcrumb");
        }

        foreach (['menu.vue', 'auto-reply.vue'] as $view) {
            $this->assertFileExists(base_path() . "/../admin/src/views/channel/official/{$view}");
        }
    }

    public function test_m6b_message_menu_seeds(): void
    {
        $menus = Db::table('menus')->whereBetween('id', [130, 135])->orderBy('id')->get()->all();
        $this->assertCount(6, $menus);

        $expected = [
            // id => [parent, type, title, name, path, component, redirect, icon, permission, sort]
            130 => [2, 1, '消息管理', 'SystemMessage', '/system/message', 'LAYOUT', '/system/message/template', 'i-svg:message-circle-more', 'system.message', 13],
            131 => [130, 2, '消息模板', 'SystemMessageTemplate', '/system/message/template', '/system/message/template/index', null, 'el-icon-Tickets', 'system.message.template.list', 1],
            132 => [130, 2, '消息日志', 'SystemMessageLog', '/system/message/log', '/system/message/log/index', null, 'el-icon-List', 'system.message.log.list', 2],
            133 => [131, 3, '新增', null, null, null, null, null, 'system.message.template.create', 1],
            134 => [131, 3, '编辑', null, null, null, null, null, 'system.message.template.update', 2],
            135 => [131, 3, '删除', null, null, null, null, null, 'system.message.template.delete', 3],
        ];
        foreach ($menus as $menu) {
            $id = (int) $menu->id;
            $this->assertSame($expected[$id], [
                (int) $menu->parent_id, (int) $menu->type, $menu->title, $menu->name, $menu->path,
                $menu->component, $menu->redirect, $menu->icon, $menu->permission, (int) $menu->sort,
            ], "菜单 {$id}");
            $this->assertSame(1, (int) $menu->status, "菜单 {$id}");
        }

        // 130 与 M4 在线管理员（120，sort 12）同属系统管理目录，sort 不得撞车
        $this->assertSame(1, Db::table('menus')->where('parent_id', 2)->where('sort', 13)->count());
        // 1.x 的「发送测试」按钮不种（spec §1.2：test-send 不做）
        $this->assertSame(0, Db::table('menus')->where('permission', 'system.message.template.send')->count());

        foreach (['template/index.vue', 'log/index.vue'] as $view) {
            $this->assertFileExists(base_path() . "/../admin/src/views/system/message/{$view}");
        }
    }

    public function test_m6b_builtin_message_template_seeds(): void
    {
        $this->assertSame(['user_register', 'payment_success', 'feedback_received'], \app\repository\message\MessageTemplateRepository::BUILTIN_CODES);

        $rows = [];
        foreach (Db::table('message_templates')->whereIn('code', ['user_register', 'payment_success'])->whereNull('deleted_at')->get()->all() as $row) {
            $rows[(string) $row->code] = $row;
        }
        $this->assertCount(2, $rows);

        $register = $rows['user_register'];
        $this->assertSame('注册成功通知', $register->name);
        $this->assertSame(1, (int) $register->status);
        $this->assertSame(1, (int) $register->site_enabled);
        $this->assertSame('注册成功', $register->site_title);
        $this->assertSame('欢迎加入，${nickname}', $register->site_content);
        // JSON 列取回时对象键序会变：assertEquals 比内容不比键序
        $this->assertEquals([['key' => 'nickname', 'name' => '昵称', 'example' => '张三']], json_decode((string) $register->variables, true));
        $this->assertEquals(['thing1' => '${nickname}'], json_decode((string) $register->wechat_official_data, true));
        $this->assertEquals(['thing1' => '${nickname}'], json_decode((string) $register->wechat_mini_data, true));

        $payment = $rows['payment_success'];
        $this->assertSame('充值成功通知', $payment->name);
        $this->assertSame(1, (int) $payment->status);
        $this->assertSame(1, (int) $payment->site_enabled);
        $this->assertSame('充值成功', $payment->site_title);
        $this->assertSame('订单 ${order_no} 已到账 ${amount} 元', $payment->site_content);
        $this->assertSame(['order_no', 'amount', 'paid_at'], array_column((array) json_decode((string) $payment->variables, true), 'key'));
        $this->assertSame(['订单号', '金额', '支付时间'], array_column((array) json_decode((string) $payment->variables, true), 'name'));
        $mapping = ['character_string1' => '${order_no}', 'amount2' => '${amount}元', 'time3' => '${paid_at}'];
        $this->assertEquals($mapping, json_decode((string) $payment->wechat_official_data, true));
        $this->assertEquals($mapping, json_decode((string) $payment->wechat_mini_data, true));

        foreach ($rows as $code => $row) {
            foreach (['sms', 'wechat_official', 'wechat_mini'] as $channel) {
                $this->assertSame(0, (int) $row->{"{$channel}_enabled"}, "{$code} 的 {$channel} 种子必须停用");
                $this->assertSame('', $row->{"{$channel}_template_id"}, "{$code} 的 {$channel} 种子不带模板 id");
            }
        }
    }

    public function test_m7a_content_tables(): void
    {
        foreach (['articles', 'article_categories', 'announcements', 'agreements', 'feedbacks'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), $table);
        }
        $this->assertTrue(Db::schema()->hasColumn('articles', 'created_by'));
        $this->assertFalse(Db::schema()->hasColumn('articles', 'admin_id'));
        $this->assertTrue(Db::schema()->hasColumn('articles', 'deleted_at'));
        $this->assertFalse(Db::schema()->hasColumn('article_categories', 'deleted_at'));
        $this->assertFalse(Db::schema()->hasColumn('agreements', 'deleted_at'));
        $this->assertTrue(Db::schema()->hasColumn('feedbacks', 'deleted_at'));
        $indexes = Db::select('SHOW INDEX FROM agreements');
        $this->assertContains('uk_code', array_column($indexes, 'Key_name'));
    }

    public function test_m7a_content_menu_seeds(): void
    {
        $ids = [7, 700, 701, 702, 703, 710, 711, 712, 713, 714, 720, 721, 722, 723, 725, 730, 731, 732, 733, 740, 741, 742, 743, 744];
        $menus = Db::table('menus')->whereIn('id', $ids)->orderBy('id')->get()->all();
        $this->assertCount(count($ids), $menus);
        $byId = [];
        foreach ($menus as $m) {
            $byId[(int) $m->id] = $m;
        }
        $this->assertSame(0, (int) $byId[7]->parent_id);
        $this->assertSame('Content', $byId[7]->name);
        $this->assertSame('agreement.list', $byId[7]->permission);
        $this->assertSame(600, (int) $byId[7]->sort);
        $this->assertSame('/content/agreement', $byId[7]->redirect);
        $this->assertSame('agreement.create', $byId[701]->permission);
        $this->assertSame('announcement.status', $byId[714]->permission);
        $this->assertSame('feedback.reply', $byId[721]->permission);
        $this->assertSame(725, (int) $byId[730]->parent_id);
        $this->assertSame('article.status', $byId[744]->permission);
        $this->assertSame(0, Db::table('menus')->where('permission', 'article_category.status')->count());
        foreach (['agreement/index.vue', 'announcement/index.vue', 'feedback/index.vue', 'article-category/index.vue', 'article/index.vue'] as $view) {
            $this->assertFileExists(base_path() . "/../admin/src/views/content/{$view}");
        }
    }

    public function test_m7a_agreement_and_feedback_template_seeds(): void
    {
        $this->assertSame(
            ['user_register', 'payment_success', 'feedback_received'],
            \app\repository\message\MessageTemplateRepository::BUILTIN_CODES
        );
        foreach (['user_agreement' => '用户协议', 'privacy_policy' => '隐私政策'] as $code => $title) {
            $row = Db::table('agreements')->where('code', $code)->first();
            $this->assertNotNull($row, $code);
            $this->assertSame($title, $row->title);
            $this->assertSame(1, (int) $row->status);
        }
        $tpl = Db::table('message_templates')->where('code', 'feedback_received')->whereNull('deleted_at')->first();
        $this->assertNotNull($tpl);
        $this->assertSame(1, (int) $tpl->site_enabled);
        $this->assertSame(0, (int) $tpl->sms_enabled);
        $this->assertSame('反馈已收到', $tpl->site_title);
        $this->assertSame('您的反馈我们已收到，将尽快为您处理，感谢您的支持！', $tpl->site_content);
        $this->assertSame('feedback', \app\service\message\MessageService::SITE_TYPES['feedback_received']);
    }

    public function test_m7b_app_tables(): void
    {
        foreach (['regions', 'app_versions', 'data_imports'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), $table);
        }
        $this->assertFalse(Db::schema()->hasColumn('regions', 'created_at'));
        $this->assertFalse(Db::schema()->hasColumn('regions', 'deleted_at'));
        $this->assertFalse(Db::schema()->hasColumn('data_imports', 'created_by'));
        $this->assertTrue(Db::schema()->hasColumn('data_imports', 'admin_id'));
        $this->assertContains('uk_code', array_column(Db::select('SHOW INDEX FROM regions'), 'Key_name'));
    }

    public function test_m7b_region_seed_matches_sql_file(): void
    {
        $sql = (string) file_get_contents(base_path() . '/database/install/regions.sql');
        preg_match_all('/^\(\d+,/m', $sql, $m);
        $this->assertGreaterThan(2800, count($m[0]));
        $this->assertSame(count($m[0]), (int) Db::table('regions')->count());
        $bj = Db::table('regions')->where('id', 110000)->first();
        $this->assertNotNull($bj);
        $this->assertSame('北京市', $bj->name);
        $this->assertNotNull(Db::table('regions')->where('id', 630000)->first(), '青海省');
        $this->assertNotNull(Db::table('regions')->where('id', 650000)->first(), '新疆');
        $this->assertGreaterThan(0, Db::table('regions')->where('parent_id', 620000)->count(), '甘肃须有市级');
        // 约定是 GB/T 2260 六位码：源数据里几个不设区的市会给出 9 位的街道，生成脚本必须滤掉，
        // 否则选到东莞这类城市时下拉里是几十个街道而不是区县。
        $this->assertSame(0, (int) Db::table('regions')->where('id', '>', 999999)->count(), '不得有 9 位码的街道行');
        $this->assertSame(0, (int) Db::table('regions')->whereRaw('CHAR_LENGTH(code) <> 6')->count());
        $this->assertSame(0, (int) Db::table('app_versions')->count());
        $this->assertSame(0, (int) Db::table('data_imports')->count());
    }

    public function test_m7b_app_menu_seeds(): void
    {
        $ids = [8, 800, 801, 802, 803, 810, 811, 812, 813];
        $menus = Db::table('menus')->whereIn('id', $ids)->orderBy('id')->get()->all();
        $this->assertCount(count($ids), $menus);
        $byId = [];
        foreach ($menus as $m) {
            $byId[(int) $m->id] = $m;
        }
        $this->assertSame(0, (int) $byId[8]->parent_id);
        $this->assertSame('Application', $byId[8]->name);
        $this->assertSame('region.list', $byId[8]->permission);
        $this->assertSame(650, (int) $byId[8]->sort);
        $this->assertSame('/app/region', $byId[8]->redirect);
        $this->assertSame('/content/region/index', $byId[800]->component);
        $this->assertSame('/content/version/index', $byId[810]->component);
        $this->assertSame('region.create', $byId[801]->permission);
        $this->assertSame('version.delete', $byId[813]->permission);
        $this->assertSame(0, Db::table('menus')->where('permission', 'like', 'dataimport.%')->count());
        $this->assertSame(0, Db::table('menus')->whereIn('permission', ['region.status', 'version.status'])->count());
        foreach (['region/index.vue', 'version/index.vue'] as $view) {
            $this->assertFileExists(base_path() . "/../admin/src/views/content/{$view}");
        }
    }

    public function test_m7c_diy_tables(): void
    {
        foreach (['diy_pages', 'diy_page_versions', 'diy_links', 'mobile_configs'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), $table);
        }
        $this->assertTrue(Db::schema()->hasColumn('diy_pages', 'deleted_at'));
        $this->assertTrue(Db::schema()->hasColumn('diy_links', 'deleted_at'));
        $this->assertFalse(Db::schema()->hasColumn('diy_page_versions', 'updated_at'));
        $this->assertFalse(Db::schema()->hasColumn('diy_page_versions', 'deleted_at'));
        $this->assertTrue(Db::schema()->hasColumn('diy_page_versions', 'created_by'));
        $this->assertFalse(Db::schema()->hasColumn('mobile_configs', 'app_intro'));
        $this->assertContains('uk_pagekey_platform', array_column(Db::select('SHOW INDEX FROM diy_pages'), 'Key_name'));
    }

    public function test_m7c_diy_seeds(): void
    {
        $this->assertSame(1, (int) Db::table('diy_pages')->where('page_key', 'home')->where('platform', 'uniapp')->count());
        $this->assertSame(1, (int) Db::table('diy_pages')->where('page_key', 'member')->where('platform', 'uniapp')->count());
        $home = Db::table('diy_pages')->where('page_key', 'home')->first();
        $this->assertNotNull($home);
        $published = json_decode((string) $home->components_published, true);
        $this->assertIsArray($published);
        $this->assertNotSame([], $published);
        $this->assertSame($home->components_draft, $home->components_published);
        $this->assertSame(1, (int) Db::table('mobile_configs')->count());
        $cfg = Db::table('mobile_configs')->first();
        $this->assertSame('#2979ff', $cfg->theme_color);
        $this->assertSame(0, (int) Db::table('diy_links')->count());
        $this->assertSame(0, (int) Db::table('diy_page_versions')->count());
        $draft = (string) $home->components_draft;
        $this->assertStringContainsString('/static/diy/home/banner.jpg', $draft);
        $this->assertStringContainsString('/static/diy/home/nav-app-market.png', $draft);
        $this->assertStringContainsString('/static/diy/home/nav-all.png', $draft);
        $this->assertStringNotContainsString('/static/diy/home/', (string) Db::table('diy_pages')->where('page_key', 'member')->value('components_published'));
        $tabbar = (string) Db::table('mobile_configs')->value('tabbar_json');
        $this->assertStringContainsString('/static/diy/tabbar/home.png', $tabbar);
        $this->assertStringContainsString('/static/diy/tabbar/my-active.png', $tabbar);
        $this->assertStringNotContainsString('/static/tabbar/', $tabbar);
        $this->assertFileExists(base_path('public/static/diy/home/banner.jpg'));
        $this->assertFileExists(base_path('public/static/diy/tabbar/home.png'));
        $this->assertSame('2.0.3', (string) config('version.version'));
    }

    public function test_m7c_diy_menu_seeds(): void
    {
        $ids = [16, 1600, 1601, 1602, 1603, 1604, 1605, 1606, 1607, 1608, 1609, 1610, 1611, 1612, 1613, 1614, 1615, 1616, 1617, 1618];
        $menus = Db::table('menus')->whereIn('id', $ids)->orderBy('id')->get()->all();
        $this->assertCount(count($ids), $menus);
        $byId = [];
        foreach ($menus as $m) {
            $byId[(int) $m->id] = $m;
        }
        $this->assertSame(0, (int) $byId[16]->parent_id);
        $this->assertSame('Diy', $byId[16]->name);
        $this->assertSame('diy.home.view', $byId[16]->permission);
        $this->assertSame(700, (int) $byId[16]->sort);
        $this->assertSame('/diy/home', $byId[16]->redirect);
        $this->assertSame('diy/decorate-list', $byId[1600]->component);
        $this->assertSame('diy/pages', $byId[1601]->component);
        $this->assertSame('diy/tabbar', $byId[1602]->component);
        $this->assertSame('diy/theme', $byId[1603]->component);
        $this->assertSame('diy/links', $byId[1604]->component);
        $this->assertSame('diy.home.save', $byId[1605]->permission);
        $this->assertSame('diy.page.create', $byId[1609]->permission);
        $this->assertSame('mobile.config.update', $byId[1614]->permission);
        $this->assertSame('mobile.config.update', $byId[1615]->permission);
        $this->assertSame('diy.link.delete', $byId[1618]->permission);
        foreach (['decorate-list.vue', 'pages.vue', 'tabbar.vue', 'theme.vue', 'links.vue'] as $view) {
            $this->assertFileExists(base_path() . "/../admin/src/views/diy/{$view}");
        }
    }

    public function test_installer_fingerprint_includes_regions_sql(): void
    {
        $source = base_path() . '/database/install';
        $dir = sys_get_temp_dir() . '/yd-fingerprint-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        try {
            foreach (['schema.sql', 'init.sql', 'regions.sql'] as $file) {
                copy($source . '/' . $file, $dir . '/' . $file);
            }
            $two = md5((string) file_get_contents($dir . '/schema.sql') . "\0" . (string) file_get_contents($dir . '/init.sql'));
            $three = md5(
                (string) file_get_contents($dir . '/schema.sql') . "\0"
                . (string) file_get_contents($dir . '/init.sql') . "\0"
                . (string) file_get_contents($dir . '/regions.sql')
            );
            $this->assertSame($three, \core\database\DatabaseInstaller::fingerprint($dir));
            $this->assertNotSame($two, $three);

            file_put_contents($dir . '/demo.sql', 'demo data must not affect the fingerprint');
            $this->assertSame($three, \core\database\DatabaseInstaller::fingerprint($dir));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
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

    public function test_m6b_message_tables_exist_with_expected_columns_and_indexes(): void
    {
        $columns = [
            'message_templates' => [
                'id', 'name', 'code', 'remark', 'status',
                'sms_enabled', 'sms_template_id', 'sms_content',
                'wechat_official_enabled', 'wechat_official_template_id', 'wechat_official_url', 'wechat_official_data',
                'wechat_mini_enabled', 'wechat_mini_template_id', 'wechat_mini_page', 'wechat_mini_data',
                'site_enabled', 'site_title', 'site_content', 'variables',
                'created_at', 'updated_at', 'deleted_at',
            ],
            'message_logs' => [
                'id', 'template_id', 'template_code', 'channel', 'user_id', 'receiver', 'variables', 'content',
                'status', 'error_msg', 'attempts', 'sent_at', 'created_at', 'updated_at',
            ],
            'user_notifications' => ['id', 'user_id', 'title', 'content', 'type', 'biz_id', 'extra', 'created_at', 'updated_at'],
            'user_notification_reads' => ['id', 'notification_id', 'user_id', 'read_at'],
        ];
        foreach ($columns as $table => $expected) {
            $this->assertTrue(Db::schema()->hasTable($table), "缺少表 {$table}");
            $this->assertSame($expected, Db::schema()->getColumnListing($table), "{$table} 列与 spec §2 不一致");
        }

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
            'PRIMARY'    => ['id'],
            'uk_code'    => ['code'],
            'idx_status' => ['status'],
        ], $indexes('message_templates'));
        $this->assertSame([
            'PRIMARY'             => ['id'],
            'idx_status_created'  => ['status', 'created_at'],
            'idx_channel_created' => ['channel', 'created_at'],
            'idx_template_code'   => ['template_code'],
            'idx_user'            => ['user_id'],
        ], $indexes('message_logs'));
        $this->assertSame([
            'PRIMARY'     => ['id'],
            'idx_user_id' => ['user_id', 'id'],
        ], $indexes('user_notifications'));
        $this->assertSame([
            'PRIMARY'              => ['id'],
            'uk_notification_user' => ['notification_id', 'user_id'],
            'idx_user'             => ['user_id'],
        ], $indexes('user_notification_reads'));

        // 默认值：模板四通道默认关、status=1；日志 status=0、attempts=0、receiver/error_msg 空串
        $now = date('Y-m-d H:i:s');
        $code = 'schema_' . bin2hex(random_bytes(6));
        $templateId = (int) Db::table('message_templates')->insertGetId(['name' => '建表自检', 'code' => $code, 'created_at' => $now, 'updated_at' => $now]);
        $logId = (int) Db::table('message_logs')->insertGetId(['template_code' => $code, 'channel' => 'sms', 'created_at' => $now, 'updated_at' => $now]);
        try {
            $template = Db::table('message_templates')->where('id', $templateId)->first();
            $this->assertSame(1, (int) $template->status);
            foreach (['sms_enabled', 'wechat_official_enabled', 'wechat_mini_enabled', 'site_enabled'] as $flag) {
                $this->assertSame(0, (int) $template->{$flag}, "{$flag} 默认应为 0");
            }
            $this->assertSame('', $template->site_title);

            $log = Db::table('message_logs')->where('id', $logId)->first();
            $this->assertSame(0, (int) $log->status);
            $this->assertSame(0, (int) $log->attempts);
            $this->assertSame('', $log->receiver);
            $this->assertSame('', $log->error_msg);

            // uk_code：同编码第二次插入必须撞唯一键（软删行同样占用编码，spec §4.1）
            try {
                Db::table('message_templates')->insert(['name' => '重复', 'code' => $code, 'created_at' => $now, 'updated_at' => $now]);
                $this->fail('uk_code 唯一索引未生效');
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $this->assertStringContainsString('uk_code', $e->getMessage());
            }
        } finally {
            Db::table('message_templates')->where('id', $templateId)->delete();
            Db::table('message_logs')->where('id', $logId)->delete();
        }
    }

    public function test_m6c_wechat_auto_replies_table(): void
    {
        $table = 'wechat_auto_replies';
        $this->assertTrue(Db::schema()->hasTable($table), "缺少表 {$table}");
        $this->assertSame([
            'id', 'type', 'keyword', 'match_type', 'reply_type', 'content',
            'status', 'sort_order', 'created_at', 'updated_at', 'deleted_at',
        ], Db::schema()->getColumnListing($table));

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
            'PRIMARY'    => ['id'],
            'idx_type'    => ['type'],
            'idx_keyword' => ['keyword'],
            'idx_status'  => ['status'],
        ], $indexes($table));
    }

    public function test_m8_system_upgrades_table(): void
    {
        $this->assertTrue(Db::schema()->hasTable('system_upgrades'));
        $this->assertSame(['id', 'version', 'applied_at'], Db::schema()->getColumnListing('system_upgrades'));
        $initSql = (string) file_get_contents(base_path() . '/database/install/init.sql');
        $this->assertStringNotContainsString('INSERT INTO `system_upgrades`', $initSql, 'init.sql 不种版本');
        $this->assertGreaterThan(0, Db::table('system_upgrades')->count(), '测试引导打标 2.0.0，InstallGuard 才能把已装环境当已安装');
        $indexes = [];
        foreach (Db::select('SHOW INDEX FROM system_upgrades') as $row) {
            $indexes[$row->Key_name][(int) $row->Seq_in_index] = $row->Column_name;
        }
        $this->assertSame(['id'], array_values($indexes['PRIMARY']));
        $this->assertSame(['version'], array_values($indexes['uk_version']));
    }

    /**
     * 代码版本要盖过所有升级目录：新装按代码版本打戳，版本落后的话，
     * 新装会挂着一个永远待应用的升级目录，而 schema.sql 里其实早就有那些改动了。
     */
    public function test_m8_code_version_covers_every_update_dir(): void
    {
        $version = (string) config('version.version');
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);

        foreach (glob(base_path() . '/database/updates/v*') ?: [] as $dir) {
            $dirVersion = ltrim(basename($dir), 'v');
            $this->assertTrue(
                version_compare($dirVersion, $version, '<='),
                "升级目录 v{$dirVersion} 比代码版本 {$version} 新，config/version.php 该跟上"
            );
        }
    }
}
