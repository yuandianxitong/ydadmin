<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\system\SystemConfigRepository;
use app\service\system\SystemConfigService;
use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线：config/global 只要登录即可访问，绝不能返回凭据（spec §6.1、§7.2）。
 * 两道闸：
 *   - 只返回 is_public=1 的启用行（白名单）；
 *   - 凭据键即使被误标公开，键名黑名单也会拦下。
 */
final class Test13_GlobalConfigNoCredentialsTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        (new SystemConfigRepository())->forgetCache();
    }

    private function seed(string $key, string $value, int $isPublic, int $status = 1): void
    {
        $now = date('Y-m-d H:i:s');
        $this->track('system_configs', (int) Db::table('system_configs')->insertGetId([
            'config_key'   => $key,
            'config_value' => $value,
            'config_group' => 'rl13',
            'config_type'  => 'string',
            'status'       => $status,
            'is_public'    => $isPublic,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]));
    }

    public function test_neither_flagged_credentials_nor_unflagged_keys_leak(): void
    {
        $s = bin2hex(random_bytes(3)); // 十六进制后缀，不会拼出 pass/token 等片段
        $this->seed("rl13_{$s}_oss_access_secret", 'RL13-CREDENTIAL', 1); // 误标公开的凭据
        $this->seed("rl13_{$s}_pay_mch_key", 'RL13-MCH-KEY', 1);         // 裸 *_key 也是凭据
        $this->seed("rl13_{$s}_internal_note", 'RL13-PRIVATE', 0);        // 非凭据，但未公开
        $this->seed("rl13_{$s}_disabled_notice", 'RL13-DISABLED', 1, 0);  // 公开，但已停用
        $this->seed("rl13_{$s}_public_notice", 'RL13-VISIBLE', 1);        // 对照组：公开的非凭据键
        (new SystemConfigRepository())->forgetCache();

        $admin = $this->actingAsAdmin();
        $data = $this->get('/adminapi/system/config/global', [], $admin->token)->assertOk()->data();

        $this->assertSame('RL13-VISIBLE', $data["rl13_{$s}_public_notice"] ?? null, '对照组应当返回');
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE);
        foreach (['RL13-CREDENTIAL', 'RL13-MCH-KEY', 'RL13-PRIVATE', 'RL13-DISABLED'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "config/global 泄露了 {$leak}");
        }
    }

    public function test_every_returned_key_is_public_enabled_and_not_a_credential(): void
    {
        $admin = $this->actingAsAdmin();
        $data = $this->get('/adminapi/system/config/global', [], $admin->token)->assertOk()->data();
        $this->assertNotSame([], $data);

        $rows = Db::table('system_configs')->whereNull('deleted_at')->whereIn('config_key', array_keys($data))
            ->get(['config_key', 'is_public', 'status'])->keyBy('config_key')->all();
        foreach (array_keys($data) as $key) {
            $row = $rows[$key] ?? null;
            $this->assertNotNull($row, "{$key} 不在 system_configs");
            $this->assertSame(1, (int) $row->is_public, "{$key} 未标记公开");
            $this->assertSame(1, (int) $row->status, "{$key} 已停用");
            $this->assertFalse(SystemConfigService::isSensitiveKey((string) $key), "{$key} 是凭据类键");
        }
    }
}
