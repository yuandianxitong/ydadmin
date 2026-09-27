<?php

declare(strict_types=1);

namespace tests\Feature\Install;

use app\controller\InstallController;
use core\contract\SuperAdminInitializer;
use core\install\Installer;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

final class InstallGuardTest extends ApiTestCase
{
    private string $workDir;

    private string $lockPath;

    private Installer $originalInstaller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalInstaller = Container::get(Installer::class);
        $this->workDir = sys_get_temp_dir() . '/yd-guard-' . bin2hex(random_bytes(4));
        mkdir($this->workDir, 0o755, true);
        $this->lockPath = $this->workDir . '/install.lock';
        Container::set(Installer::class, new Installer(
            Container::get(SuperAdminInitializer::class),
            base_path() . '/database/install',
            $this->workDir . '/.env',
            base_path() . '/.env.example',
            $this->lockPath,
        ));
        Container::set(InstallController::class, Container::make(InstallController::class));
        Db::table('system_upgrades')->delete();
    }

    protected function tearDown(): void
    {
        Container::set(Installer::class, $this->originalInstaller);
        Container::set(InstallController::class, Container::make(InstallController::class));
        if ((int) Db::table('system_upgrades')->count() === 0) {
            Db::table('system_upgrades')->insert([
                'version'    => '2.0.0',
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function test_uninstalled_adminapi_is_503_with_installed_false(): void
    {
        $response = $this->get('/adminapi/health');

        $this->assertSame(503, $response->status());
        $this->assertSame(503, $response->code());
        $this->assertSame(lang('install.not_installed'), $response->message());
        $this->assertSame(['installed' => false], $response->data());
    }

    public function test_uninstalled_html_redirects_to_wizard(): void
    {
        $response = $this->get('/admin', [], null, ['Accept' => 'text/html']);

        $this->assertSame(302, $response->status());
        $this->assertSame('/install/', $response->header('Location'));
    }

    public function test_uninstalled_wizard_stays_reachable(): void
    {
        $page = $this->get('/install');
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('text/html', (string) $page->header('Content-Type'));

        $env = $this->get('/install/environment');
        $this->assertSame(200, $env->status());
        $this->assertSame(200, $env->code());
    }

    public function test_default_lock_file_is_under_config(): void
    {
        $path = (new \ReflectionClass($this->originalInstaller))->getProperty('lockPath')->getValue($this->originalInstaller);

        $this->assertSame(base_path('config/install.lock'), $path);
        $this->assertSame($path, config('install.lock'));
    }

    public function test_installed_passes_through(): void
    {
        file_put_contents($this->lockPath, date('c'));

        $response = $this->get('/adminapi/health');

        $this->assertSame(200, $response->status());
        $this->assertSame(200, $response->code());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
