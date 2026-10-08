<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\SystemConfigRepository;
use support\Cache;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;

final class SystemConfigApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/config';

    protected function tearDown(): void
    {
        parent::tearDown();
        (new SystemConfigRepository())->forgetCache(); // 夹具插入的配置行已删，缓存也要清
    }

    /** @param array<string, mixed> $attributes */
    private function createConfig(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('system_configs')->insertGetId(array_merge([
            'config_key'   => 'cfg_' . bin2hex(random_bytes(4)),
            'config_value' => '',
            'config_group' => 'cfg_test',
            'config_type'  => 'string',
            'sort_order'   => 0,
            'status'       => 1,
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $attributes));
        $this->track('system_configs', $id);

        return $id;
    }

    private function idOf(string $key): int
    {
        return (int) Db::table('system_configs')->where('config_key', $key)->value('id');
    }

    private function storedValue(int $id): string
    {
        return (string) Db::table('system_configs')->where('id', $id)->value('config_value');
    }

    public function test_index_returns_raw_enabled_rows_of_a_group(): void
    {
        $admin = $this->actingAsAdmin(['system.config.list']);

        $rows = $this->get(self::BASE, [], $admin->token)->assertOk()->data();
        $this->assertCount(18, $rows, '不传 group 时默认 basic');
        $this->assertSame('site_name', $rows[0]['config_key'], '按 sort_order 升序');
        $byKey = array_column($rows, null, 'config_key');
        $this->assertSame('1', $byKey['login_captcha']['config_value'], 'index 返回库里的原始字符串，不做类型转换');
        $this->assertSame('5', $byKey['login_max_retry']['config_value']);
        $this->assertSame(1, $byKey['site_name']['is_public']);

        $second = $this->createConfig(['sort_order' => 2]);
        $first = $this->createConfig(['sort_order' => 1]);
        $this->createConfig(['sort_order' => 0, 'status' => 0]);
        $rows = $this->get(self::BASE, ['group' => 'cfg_test'], $admin->token)->assertOk()->data();
        $this->assertSame([$first, $second], array_column($rows, 'id'), '只含启用行');
    }

    public function test_groups_are_the_five_contract_groups_with_translated_labels(): void
    {
        $admin = $this->actingAsAdmin(); // PermissionSkip：登录即可

        $this->assertSame(
            ['basic' => '基础配置', 'email' => '邮件配置', 'sms' => '短信配置', 'storage' => '存储配置', 'payment' => '支付配置'],
            $this->get(self::BASE . '/groups', [], $admin->token)->assertOk()->data()
        );
        $english = $this->get(self::BASE . '/groups', [], $admin->token, ['think-lang' => 'en'])->assertOk()->data();
        $this->assertSame('Basic Settings', $english['basic']);
    }

    public function test_show_returns_the_raw_row(): void
    {
        $admin = $this->actingAsAdmin(['system.config.list']);

        $row = $this->get(self::BASE . '/' . $this->idOf('login_captcha'), [], $admin->token)->assertOk()->data();
        $this->assertSame('login_captcha', $row['config_key']);
        $this->assertSame('1', $row['config_value']);

        $disabled = $this->createConfig(['status' => 0]);
        $this->assertSame($disabled, $this->get(self::BASE . "/{$disabled}", [], $admin->token)->assertOk()->data()['id'], '详情不限状态（照 TP8）');
        $this->assertSame(lang('business.config_not_found'), $this->get(self::BASE . '/999999', [], $admin->token)->assertCode(400)->message());
    }

    public function test_update_serializes_by_type_and_refreshes_global_after_commit(): void
    {
        $admin = $this->actingAsAdmin(['system.config.update']);
        $this->rememberConfig('site_name');
        $siteId = $this->idOf('site_name');
        // 预热公开配置缓存：若提交后没清缓存，下面读 global 会拿到旧值
        $this->assertSame('元点Admin', $this->get(self::BASE . '/global', [], $admin->token)->assertOk()->data()['site_name']);

        $response = $this->put(self::BASE . "/{$siteId}", ['config_value' => '新站名'], $admin->token)->assertOk();
        $this->assertTrue($response->data(), '契约 §2.7：data 为 true');
        $this->assertSame('新站名', $this->storedValue($siteId));
        $this->assertSame('新站名', $this->get(self::BASE . '/global', [], $admin->token)->assertOk()->data()['site_name']);

        $json = $this->createConfig(['config_type' => 'json', 'config_value' => '[]']);
        $this->put(self::BASE . "/{$json}", ['config_value' => ['a' => 1, 'b' => [true]]], $admin->token)->assertOk();
        $this->assertSame('{"a":1,"b":[true]}', $this->storedValue($json));
        $this->put(self::BASE . "/{$json}", ['config_value' => '{"c":2}'], $admin->token)->assertOk();
        $this->assertSame('{"c":2}', $this->storedValue($json), '已是合法 JSON 的字符串原样存，不二次编码');
        $this->put(self::BASE . "/{$json}", ['config_value' => 'plain'], $admin->token)->assertOk();
        $this->assertSame('"plain"', $this->storedValue($json));

        $bool = $this->createConfig(['config_type' => 'boolean', 'config_value' => '0']);
        $this->put(self::BASE . "/{$bool}", ['config_value' => true], $admin->token)->assertOk();
        $this->assertSame('1', $this->storedValue($bool));
        $this->put(self::BASE . "/{$bool}", ['config_value' => false], $admin->token)->assertOk();
        $this->assertSame('0', $this->storedValue($bool));

        $stringKey = 'cfg_str_' . bin2hex(random_bytes(3));
        $string = $this->createConfig(['config_key' => $stringKey]);
        $this->assertSame(
            lang('business.config_value_invalid', ['key' => $stringKey]),
            $this->put(self::BASE . "/{$string}", ['config_value' => ['x']], $admin->token)->assertCode(400)->message()
        );
        $this->assertSame('', $this->storedValue($string));
        $this->put(self::BASE . "/{$string}", ['config_value' => ''], $admin->token)->assertOk(); // present：允许清空
        $this->put(self::BASE . "/{$string}", [], $admin->token)->assertCode(422);
        $this->assertSame(lang('business.config_not_found'), $this->put(self::BASE . '/999999', ['config_value' => 'x'], $admin->token)->assertCode(400)->message());
    }

    /**
     * Task 7 ruling 1：storage_upload_max_size / storage_image_max_size 是管理员能改的上传大小上限（MB），
     * 不能被改到 Workerman 的 max_package_size（config/server.php）都装不下的值——否则请求在应用代码
     * 跑之前就被断开连接，管理员看到的是一片诊断不出原因的上传失败。
     */
    public function test_upload_size_configs_reject_values_the_server_wont_accept(): void
    {
        $admin = $this->actingAsAdmin(['system.config.update']);

        foreach (['storage_upload_max_size', 'storage_image_max_size'] as $key) {
            $this->rememberConfig($key);
            $id = $this->idOf($key);

            // ruling 1 举的例子：把上限调到 50MB。max_package_size 已经放宽到 100MiB，
            // 这个值必须能正常写入——旧的 10MiB 包体上限会让它连库都写不进去合理生效。
            $this->put(self::BASE . "/{$id}", ['config_value' => '50'], $admin->token)->assertOk();
            $this->assertSame('50', $this->storedValue($id));

            // 大到无论怎么留余量都会超过 max_package_size 的值，必须被业务校验挡住，不能覆盖掉上一次成功写入的值
            $response = $this->put(self::BASE . "/{$id}", ['config_value' => '99999'], $admin->token)->assertCode(400);
            $this->assertSame(lang('business.upload_size_exceeds_package_limit', ['key' => $key, 'max' => $this->maxUploadSizeMb()]), $response->message());
            $this->assertSame('50', $this->storedValue($id), '超限的值不能写进库');

            $this->put(self::BASE . "/{$id}", ['config_value' => $this->maxUploadSizeMb()], $admin->token)->assertOk();
            $this->put(self::BASE . "/{$id}", ['config_value' => (string) ($this->maxUploadSizeMb() + 1)], $admin->token)->assertCode(400);
        }
    }

    /** 服务器的 max_package_size 要在种子默认的上传上限（10MB）之上留出足够大的余量，否则默认配置本身就是坏的。 */
    public function test_server_package_size_leaves_comfortable_headroom_over_the_seeded_upload_limits(): void
    {
        $packageMb = intdiv((int) config('server.max_package_size'), 1024 * 1024);
        $this->assertGreaterThanOrEqual(60, $packageMb, 'max_package_size 至少要能装下 50MB 的上传上限再加上协议开销余量');
        $this->assertGreaterThan(10, $this->maxUploadSizeMb(), '管理员能填的上限要明显大于种子默认值 10MB，否则等于没改');
    }

    /** 与 SystemConfigService::maxUploadSizeMb() 相同的换算，只在测试里重复一次算法，不反射私有方法。 */
    private function maxUploadSizeMb(): int
    {
        return max(1, intdiv((int) config('server.max_package_size') - 20 * 1024 * 1024, 1024 * 1024));
    }

    public function test_batch_update_is_all_or_nothing(): void
    {
        $admin = $this->actingAsAdmin(['system.config.update']);
        $this->rememberConfig('site_name', 'site_icp');
        $this->get(self::BASE . '/global', [], $admin->token)->assertOk(); // 预热缓存

        $failed = $this->post(self::BASE . '/batch-update', ['configs' => [
            ['config_key' => 'site_name', 'config_value' => '批量站名'],
            ['config_key' => 'cfg_no_such_key', 'config_value' => '1'],
        ]], $admin->token)->assertCode(400);
        $this->assertSame(lang('business.config_key_not_found', ['key' => 'cfg_no_such_key']), $failed->message());
        $this->assertSame('元点Admin', $this->storedValue($this->idOf('site_name')), '未知键使整批回滚');

        $ok = $this->post(self::BASE . '/batch-update', ['configs' => [
            ['config_key' => 'site_name', 'config_value' => '批量站名'],
            ['config_key' => 'site_icp', 'config_value' => '京ICP备00000000号'],
        ]], $admin->token)->assertOk();
        $this->assertTrue($ok->data());
        $this->assertSame('批量站名', $this->storedValue($this->idOf('site_name')));
        $this->assertSame('京ICP备00000000号', $this->storedValue($this->idOf('site_icp')));
        $this->assertSame('批量站名', $this->get(self::BASE . '/global', [], $admin->token)->assertOk()->data()['site_name'], '提交后清了配置缓存');

        // 上传大小上限的校验对批量更新同样生效（两条路径共用 serializeValue()）
        $this->rememberConfig('storage_upload_max_size');
        $overLimit = $this->post(self::BASE . '/batch-update', ['configs' => [
            ['config_key' => 'storage_upload_max_size', 'config_value' => '99999'],
        ]], $admin->token)->assertCode(400);
        $this->assertSame(
            lang('business.upload_size_exceeds_package_limit', ['key' => 'storage_upload_max_size', 'max' => $this->maxUploadSizeMb()]),
            $overLimit->message()
        );

        $this->post(self::BASE . '/batch-update', ['configs' => []], $admin->token)->assertCode(422);
        $errors = $this->post(self::BASE . '/batch-update', ['configs' => [['config_value' => 'x']]], $admin->token)->assertCode(422)->data()['errors'];
        $this->assertSame(lang('validation.config_key_require'), $errors['configs.0.config_key']);
        $errors = $this->post(self::BASE . '/batch-update', ['configs' => [['config_key' => 'site_name']]], $admin->token)->assertCode(422)->data()['errors'];
        $this->assertSame(lang('validation.config_value_present'), $errors['configs.0.config_value']);
    }

    public function test_clear_cache_only_clears_config_caches(): void
    {
        $admin = $this->actingAsAdmin(); // PermissionSkip：登录即可（照 TP8）
        $repo = new SystemConfigRepository();
        $epoch = (int) (Redis::get('system_config.epoch') ?: 0);
        $repo->getAllConfigs();
        $repo->getPublicConfigs();
        $this->assertTrue(Cache::has('system_config.all.' . $epoch));
        $this->assertTrue(Cache::has('system_config.public.' . $epoch));
        Cache::set('dict.cfg_canary', ['x'], 300);

        try {
            $this->post(self::BASE . '/clear-cache', [], $admin->token)->assertOk();

            $next = (int) (Redis::get('system_config.epoch') ?: 0);
            $this->assertNotSame($epoch, $next);
            $this->assertFalse(Cache::has('system_config.all.' . $next));
            $this->assertFalse(Cache::has('system_config.public.' . $next));
            Cache::set('system_config.all.' . $epoch, ['site_name' => 'stale-plant'], 60);
            $this->assertNotSame('stale-plant', $repo->getAllConfigs()['site_name'] ?? null);
            $this->assertSame(['x'], Cache::get('dict.cfg_canary'), '配置以外的缓存不受影响');
            $this->get('/adminapi/auth/info', [], $admin->token)->assertOk();
        } finally {
            Cache::delete('dict.cfg_canary');
        }
    }

    public function test_reads_and_writes_require_their_permissions(): void
    {
        $siteId = $this->idOf('site_name');
        $nobody = $this->actingAsAdmin();
        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->get(self::BASE . "/{$siteId}", [], $nobody->token)->assertCode(403);

        $reader = $this->actingAsAdmin(['system.config.list']);
        $this->put(self::BASE . "/{$siteId}", ['config_value' => '越权'], $reader->token)->assertCode(403);
        $this->post(self::BASE . '/batch-update', ['configs' => [['config_key' => 'site_name', 'config_value' => '越权']]], $reader->token)->assertCode(403);
        $this->assertSame('元点Admin', $this->storedValue($siteId));
    }
}
