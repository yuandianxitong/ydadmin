<?php

declare(strict_types=1);

namespace tests\Feature\Plugin;

use app\repository\plugin\PluginRepository;
use app\service\plugin\PluginService;
use core\exception\BusinessException;
use core\plugin\PluginStatus;
use core\plugin\saga\PluginSagaRunner;
use support\Container;
use support\Db;
use tests\Feature\Plugin\Support\ImmediatePluginSagaRunner;
use tests\TestCase;

final class PluginInstallEnableTest extends TestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginDir = base_path('plugins/probe');
        $this->cleanup();
        $this->writeFixture();

        \Plugin\Probe\InstallHook::$installs = 0;
        \Plugin\Probe\InstallHook::$enables = 0;
        \Plugin\Probe\InstallHook::$disables = 0;
    }

    protected function tearDown(): void
    {
        Container::set(PluginSagaRunner::class, Container::make(PluginSagaRunner::class));
        $this->cleanup();
        parent::tearDown();
    }

    public function test_install_enable_and_disable_never_call_tenant_hooks(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $menus = Db::table('menus')->count();

        $installed = $service->install('probe');
        $this->assertSame(PluginStatus::ENABLED, $installed['status']);
        $this->assertSame('probe', $installed['code']);
        $this->assertSame('1.0.0', $installed['version']);
        $this->assertSame(PluginStatus::ENABLED, $this->statusOf('probe'));
        $this->assertSame(1, \Plugin\Probe\InstallHook::$installs);

        $disabled = $service->disable('probe');
        $this->assertSame(PluginStatus::DISABLED, $disabled['status']);
        $this->assertSame(PluginStatus::DISABLED, $this->statusOf('probe'));

        $enabled = $service->enable('probe');
        $this->assertSame(PluginStatus::ENABLED, $enabled['status']);
        $this->assertSame(PluginStatus::ENABLED, $this->statusOf('probe'));

        $this->assertSame(1, \Plugin\Probe\InstallHook::$installs);
        $this->assertSame(0, \Plugin\Probe\InstallHook::$enables);
        $this->assertSame(0, \Plugin\Probe\InstallHook::$disables);
        $this->assertSame($menus, Db::table('menus')->count());
    }

    public function test_disable_refuses_when_an_enabled_plugin_depends_on_it(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');

        (new PluginRepository())->create([
            'code' => 'needs-probe',
            'name' => 'needs-probe',
            'version' => '1.0.0',
            'kind' => 'plugin',
            'depends' => ['probe'],
            'manifest' => ['code' => 'needs-probe'],
            'status' => PluginStatus::ENABLED,
        ]);

        try {
            $service->disable('probe');
            $this->fail('应当拒绝禁用被依赖的插件');
        } catch (BusinessException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertStringContainsString('插件被依赖，无法禁用', $e->getMessage());
        } finally {
            Db::table('plugins')->where('code', 'needs-probe')->delete();
        }

        $this->assertSame(PluginStatus::ENABLED, $this->statusOf('probe'));
        $this->assertSame(0, \Plugin\Probe\InstallHook::$enables);
        $this->assertSame(0, \Plugin\Probe\InstallHook::$disables);
    }

    public function test_enable_rejects_a_plugin_that_is_not_disabled(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');

        try {
            $service->enable('probe');
            $this->fail('已启用的插件不应再被启用');
        } catch (BusinessException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $this->assertSame(1, \Plugin\Probe\InstallHook::$installs);
        $this->assertSame(0, \Plugin\Probe\InstallHook::$enables);
    }

    public function test_install_rejects_a_missing_manifest_before_inserting(): void
    {
        $service = $this->serviceWithImmediateRunner();
        unlink($this->pluginDir . '/plugin.json');

        try {
            $service->install('probe');
            $this->fail('缺少 plugin.json 时不应安装');
        } catch (BusinessException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, Db::table('plugins')->where('code', 'probe')->count());
    }

    public function test_installing_an_enabled_plugin_again_does_not_call_install(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');

        try {
            $service->install('probe');
            $this->fail('已启用的插件不应重复安装');
        } catch (BusinessException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $this->assertSame(1, \Plugin\Probe\InstallHook::$installs);
    }

    private function serviceWithImmediateRunner(): PluginService
    {
        $runner = Container::make(ImmediatePluginSagaRunner::class);
        Container::set(PluginSagaRunner::class, $runner);

        return Container::make(PluginService::class);
    }

    private function statusOf(string $code): int
    {
        return (int) Db::table('plugins')->where('code', $code)->value('status');
    }

    private function writeFixture(): void
    {
        mkdir($this->pluginDir . '/src', 0o755, true);
        file_put_contents($this->pluginDir . '/plugin.json', json_encode([
            'code' => 'probe',
            'name' => 'Probe',
            'version' => '1.0.0',
            'kind' => 'plugin',
            'psr4' => ['Plugin\\Probe\\' => 'src/'],
            'lifecycle' => 'Plugin\\Probe\\InstallHook',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        file_put_contents($this->pluginDir . '/src/InstallHook.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Plugin\Probe;

use core\plugin\contracts\LifecycleHook;

final class InstallHook implements LifecycleHook
{
    public static int $installs = 0;
    public static int $enables = 0;
    public static int $disables = 0;

    public function install(): void
    {
        self::$installs++;
    }

    public function uninstall(): void
    {
    }

    public function upgrade(string $from, string $to): void
    {
    }

    public function enable(int $tenantId): void
    {
        self::$enables++;
    }

    public function disable(int $tenantId): void
    {
        self::$disables++;
    }
}
PHP);
        // 钩子类只在本用例期间存在，直接 require，不依赖自动加载器是否已登记 psr4。
        if (!class_exists(\Plugin\Probe\InstallHook::class, false)) {
            require $this->pluginDir . '/src/InstallHook.php';
        }
    }

    private function cleanup(): void
    {
        $ids = Db::table('plugins')->whereIn('code', ['probe', 'needs-probe'])->pluck('id')->all();
        if ($ids !== []) {
            Db::table('plugin_sagas')->whereIn('plugin_id', $ids)->delete();
        }
        Db::table('plugin_sagas')->where('code', 'probe')->delete();
        Db::table('plugins')->whereIn('code', ['probe', 'needs-probe'])->delete();
        $this->removeDir($this->pluginDir);
        @rmdir(dirname($this->pluginDir));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
