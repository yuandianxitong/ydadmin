<?php

declare(strict_types=1);

namespace tests\Feature\Plugin;

use core\auth\TokenManager;
use core\plugin\PluginPaths;
use core\plugin\PluginStatus;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

final class PluginManageApiTest extends ApiTestCase
{
    use ConfigOverride;

    private string $code = '';

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideConfig('auth.jwt.admin.key', bin2hex(random_bytes(32)));
        TokenManager::flushInstances();
        $this->code = 'shelf-' . bin2hex(random_bytes(3));
        $this->dir = PluginPaths::dir($this->code);
        $this->cleanup();
        $this->writeFixture();
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanup();
            parent::tearDown();
        } finally {
            $this->restoreConfig();
            TokenManager::flushInstances();
        }
    }

    public function test_list_requires_auth_and_permission(): void
    {
        $this->get('/adminapi/plugin/list')->assertCode(401);

        $denied = $this->actingAsAdmin([]);
        $this->get('/adminapi/plugin/list', token: $denied->token)->assertCode(403);
    }

    public function test_disk_install_disable_enable_and_enabled_purge(): void
    {
        $admin = $this->actingAsAdmin('super');

        $before = $this->get('/adminapi/plugin/list', token: $admin->token)->assertCode(200)->json();
        $disk = $this->item($before);
        $this->assertSame(0, $disk['status']);
        $this->assertSame('other', $disk['category']);
        $this->assertSame('货架探针', $disk['name']);

        $this->post('/adminapi/plugin/install', ['code' => $this->code], $admin->token)->assertCode(200);
        $installed = $this->item($this->get('/adminapi/plugin/list', token: $admin->token)->assertCode(200)->json());
        $this->assertSame(PluginStatus::ENABLED, $installed['status']);
        $this->assertSame('', $installed['last_error']);

        Db::table('plugins')->where('code', $this->code)->update(['last_error' => '上一轮失败']);
        $this->post('/adminapi/plugin/disable', ['code' => $this->code], $admin->token)->assertCode(200);
        $disabled = $this->item($this->get('/adminapi/plugin/list', token: $admin->token)->assertCode(200)->json());
        $this->assertSame(PluginStatus::DISABLED, $disabled['status']);
        $this->assertSame('', $disabled['last_error']);

        Db::table('plugins')->where('code', $this->code)->update(['last_error' => '上一轮失败']);
        $this->post('/adminapi/plugin/enable', ['code' => $this->code], $admin->token)->assertCode(200);
        $enabled = $this->item($this->get('/adminapi/plugin/list', token: $admin->token)->assertCode(200)->json());
        $this->assertSame(PluginStatus::ENABLED, $enabled['status']);
        $this->assertSame('', $enabled['last_error']);

        $purged = $this->post('/adminapi/plugin/purge', ['code' => $this->code], $admin->token);
        $purged->assertCode(409);
        $this->assertStringContainsString('plugin:disable', (string) ($purged->json()['message'] ?? ''));
        $this->assertSame(PluginStatus::ENABLED, (int) Db::table('plugins')->where('code', $this->code)->value('status'));
    }

    /** @param array<string, mixed> $body */
    private function item(array $body): array
    {
        foreach ($body['data']['list'] ?? [] as $item) {
            if (($item['code'] ?? '') === $this->code) {
                return $item;
            }
        }
        $this->fail('插件列表里没有 ' . $this->code);
    }

    private function writeFixture(): void
    {
        mkdir($this->dir . '/app', 0755, true);
        file_put_contents($this->dir . '/plugin.json', json_encode([
            'code' => $this->code,
            'name' => '货架探针',
            'description' => '用来验证插件管理页的探针',
            'version' => '1.0.0',
            'kind' => 'plugin',
            'category' => 'other',
            'hosts' => ['framework-webman'],
            'psr4' => ['Plugin\\ShelfProbe\\' => 'app/'],
            'lifecycle' => 'Plugin\\ShelfProbe\\Lifecycle',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        file_put_contents($this->dir . '/app/Lifecycle.php', <<<'PHP'
<?php
namespace Plugin\ShelfProbe;

final class Lifecycle implements \core\plugin\contracts\LifecycleHook
{
    public function install(): void {}
    public function uninstall(): void {}
    public function upgrade(string $from, string $to): void {}
}
PHP);
    }

    private function cleanup(): void
    {
        if ($this->code === '') {
            return;
        }
        $ids = Db::table('plugins')->where('code', $this->code)->pluck('id')->all();
        if ($ids !== []) {
            Db::table('plugin_sagas')->whereIn('plugin_id', $ids)->delete();
        }
        Db::table('plugin_sagas')->where('code', $this->code)->delete();
        Db::table('plugins')->where('code', $this->code)->delete();
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->dir);
    }
}
