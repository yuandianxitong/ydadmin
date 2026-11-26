<?php

declare(strict_types=1);

namespace tests\Feature\Plugin;

use app\repository\plugin\PluginRepository;
use app\service\plugin\adapter\PluginRouteGeneration;
use app\service\plugin\PluginService;
use core\exception\BusinessException;
use core\plugin\PluginStatus;
use core\plugin\saga\PluginSagaRunner;
use support\Container;
use support\Db;
use tests\Feature\Plugin\Support\ImmediatePluginSagaRunner;
use tests\Feature\Plugin\Support\ProbeLedger;
use tests\Support\ConfigOverride;
use tests\TestCase;

final class PluginInstallEnableTest extends TestCase
{
    use ConfigOverride;

    private string $pluginDir;

    private string $dependentDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginDir = base_path('plugins/probe');
        $this->dependentDir = base_path('plugins/needs-probe');
        $this->cleanup();
        $this->writeFixture();

        ProbeLedger::reset();
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
        // 钩子类没有被预加载：计数为 1 说明 saga 经 ClassLoader 登记的 psr4 找到了它。
        $this->assertSame(1, ProbeLedger::$installs);

        $disabled = $service->disable('probe');
        $this->assertSame(PluginStatus::DISABLED, $disabled['status']);
        $this->assertSame(PluginStatus::DISABLED, $this->statusOf('probe'));

        $enabled = $service->enable('probe');
        $this->assertSame(PluginStatus::ENABLED, $enabled['status']);
        $this->assertSame(PluginStatus::ENABLED, $this->statusOf('probe'));

        $this->assertSame(1, ProbeLedger::$installs);
        $this->assertSame(0, ProbeLedger::$enables);
        $this->assertSame(0, ProbeLedger::$disables);
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
        $this->assertSame(0, ProbeLedger::$enables);
        $this->assertSame(0, ProbeLedger::$disables);
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

        $this->assertSame(1, ProbeLedger::$installs);
        $this->assertSame(0, ProbeLedger::$enables);
    }

    public function test_enable_rechecks_that_the_dependencies_are_enabled(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');

        $this->writeDependentFixture();
        (new PluginRepository())->create([
            'code' => 'needs-probe',
            'name' => 'needs-probe',
            'version' => '1.0.0',
            'kind' => 'plugin',
            'depends' => ['probe'],
            'manifest' => ['code' => 'needs-probe', 'depends' => ['probe']],
            'status' => PluginStatus::ENABLED,
        ]);

        $service->disable('needs-probe');
        $service->disable('probe');
        $this->assertSame(PluginStatus::DISABLED, $this->statusOf('needs-probe'));
        $this->assertSame(PluginStatus::DISABLED, $this->statusOf('probe'));

        try {
            $service->enable('needs-probe');
            $this->fail('依赖未启用时不应启用依赖它的插件');
        } catch (BusinessException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertStringContainsString('依赖', $e->getMessage());
        }

        $this->assertSame(PluginStatus::DISABLED, $this->statusOf('needs-probe'));
        $this->assertSame(0, ProbeLedger::$enables);
    }

    public function test_install_rejects_a_missing_manifest_before_inserting(): void
    {
        $service = $this->serviceWithImmediateRunner();
        unlink($this->pluginDir . '/plugin.json');

        try {
            $service->install('probe');
            $this->fail('缺少 plugin.json 时不应安装');
        } catch (BusinessException $e) {
            $this->assertSame(409, $e->getCode());
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

        $this->assertSame(1, ProbeLedger::$installs);
    }

    public function test_reinstalling_a_failed_plugin_refreshes_name_version_and_manifest(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');
        $this->assertSame(1, ProbeLedger::$installs);

        Db::table('plugins')->where('code', 'probe')->update(['status' => PluginStatus::FAILED]);
        $this->writeManifest(['name' => 'Probe Two', 'version' => '1.0.1']);

        $reinstalled = $service->install('probe');

        $this->assertSame(PluginStatus::ENABLED, $reinstalled['status']);
        $this->assertSame('1.0.1', $reinstalled['version']);
        $this->assertSame(2, ProbeLedger::$installs);

        $row = (array) Db::table('plugins')->where('code', 'probe')->first();
        $this->assertSame(PluginStatus::ENABLED, (int) $row['status']);
        $this->assertSame('Probe Two', $row['name']);
        $this->assertSame('1.0.1', $row['version']);
        $manifest = json_decode((string) $row['manifest'], true);
        $this->assertSame('1.0.1', $manifest['version']);
        $this->assertSame('Probe Two', $manifest['name']);
    }

    public function test_operations_are_refused_when_a_master_runs_but_no_worker_registered_a_fingerprint(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $pidFile = tempnam(sys_get_temp_dir(), 'plugin-master-pid-');
        $this->assertNotFalse($pidFile);
        file_put_contents($pidFile, (string) getmypid());
        $this->overrideConfig('server.pid_file', $pidFile);

        try {
            foreach (['install', 'enable', 'disable'] as $method) {
                try {
                    $service->{$method}('probe');
                    $this->fail("{$method} 应当被拒绝");
                } catch (BusinessException $e) {
                    $this->assertSame(409, $e->getCode(), $method);
                    $this->assertSame(
                        '未登记插件指纹的 worker，不能对运行中的 master 执行插件操作',
                        $e->getMessage(),
                        $method
                    );
                }
            }

            $this->assertSame(0, Db::table('plugins')->where('code', 'probe')->count());
        } finally {
            $this->restoreConfig();
            @unlink($pidFile);
        }
    }

    public function test_successful_enable_and_disable_clear_the_previous_last_error(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');
        $stale = 'reload 未在 60s 内生效：仍有存活 worker 报告与期望不一致的指纹';

        Db::table('plugins')->where('code', 'probe')->update(['last_error' => $stale]);
        $service->disable('probe');
        $this->assertSame('', (string) Db::table('plugins')->where('code', 'probe')->value('last_error'));

        Db::table('plugins')->where('code', 'probe')->update(['last_error' => $stale]);
        $service->enable('probe');
        $this->assertSame('', (string) Db::table('plugins')->where('code', 'probe')->value('last_error'));
        $this->assertSame(PluginStatus::ENABLED, $this->statusOf('probe'));
    }

    public function test_uninstall_clears_last_error_and_keeps_the_plugin_directory(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');
        $service->disable('probe');
        Db::table('plugins')->where('code', 'probe')->update([
            'last_error' => 'reload 未在 60s 内生效',
        ]);

        $result = $service->uninstall('probe');

        $this->assertSame('uninstalled', $result['status']);
        $this->assertSame(PluginStatus::UNINSTALLED, $this->statusOf('probe'));
        $this->assertSame('', (string) Db::table('plugins')->where('code', 'probe')->value('last_error'));
        $this->assertFileExists($this->pluginDir . '/plugin.json');
    }

    public function test_purge_of_an_enabled_plugin_tells_the_operator_to_disable_first(): void
    {
        $service = $this->serviceWithImmediateRunner();
        $service->install('probe');

        try {
            $service->uninstall('probe', true);
            $this->fail('启用状态下不应直接 purge');
        } catch (BusinessException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertStringContainsString('php webman plugin:disable probe', $e->getMessage());
            $this->assertStringContainsString('php webman plugin:uninstall probe --purge', $e->getMessage());
        }

        $this->assertSame(PluginStatus::ENABLED, $this->statusOf('probe'));
        $this->assertFileExists($this->pluginDir . '/plugin.json');
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

    /** @param array<string, mixed> $override */
    private function writeManifest(array $override = []): void
    {
        file_put_contents($this->pluginDir . '/plugin.json', json_encode(array_merge([
            'code' => 'probe',
            'name' => 'Probe',
            'version' => '1.0.0',
            'kind' => 'plugin',
            'psr4' => ['Plugin\\Probe\\' => 'src/'],
            'lifecycle' => 'Plugin\\Probe\\InstallHook',
        ], $override), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function writeFixture(): void
    {
        mkdir($this->pluginDir . '/src', 0o755, true);
        $this->writeManifest();

        // 钩子类只在插件目录里，测试不预加载它；计数写进 ProbeLedger。
        file_put_contents($this->pluginDir . '/src/InstallHook.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Plugin\Probe;

use core\plugin\contracts\LifecycleHook;
use tests\Feature\Plugin\Support\ProbeLedger;

final class InstallHook implements LifecycleHook
{
    public function install(): void
    {
        ProbeLedger::$installs++;
    }

    public function uninstall(): void
    {
    }

    public function upgrade(string $from, string $to): void
    {
    }

    public function enable(int $tenantId): void
    {
        ProbeLedger::$enables++;
    }

    public function disable(int $tenantId): void
    {
        ProbeLedger::$disables++;
    }
}
PHP);
    }

    private function writeDependentFixture(): void
    {
        mkdir($this->dependentDir, 0o755, true);
        file_put_contents($this->dependentDir . '/plugin.json', json_encode([
            'code' => 'needs-probe',
            'name' => 'needs-probe',
            'version' => '1.0.0',
            'kind' => 'plugin',
            'depends' => ['probe'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function cleanup(): void
    {
        $ids = Db::table('plugins')->whereIn('code', ['probe', 'needs-probe'])->pluck('id')->all();
        if ($ids !== []) {
            Db::table('plugin_sagas')->whereIn('plugin_id', $ids)->delete();
        }
        Db::table('plugin_sagas')->whereIn('code', ['probe', 'needs-probe'])->delete();
        Db::table('plugins')->whereIn('code', ['probe', 'needs-probe'])->delete();
        Db::table('system_configs')->where('config_key', PluginRouteGeneration::KEY)->delete();
        $this->removeDir($this->pluginDir);
        $this->removeDir($this->dependentDir);
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
