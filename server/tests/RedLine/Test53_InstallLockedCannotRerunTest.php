<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\command\InstallCommand;
use app\controller\InstallController;
use core\contract\SuperAdminInitializer;
use core\install\Installer;
use support\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\ApiTestCase;

/**
 * 红线 Test53：install.lock 一旦存在，浏览器向导与 CLI 都不得再次安装。
 *
 * 会让本用例失败的生产改动：拿掉 isInstalled 闸、把已安装做成 401、或命令忽略 Container 里的 Installer。
 */
final class Test53_InstallLockedCannotRerunTest extends ApiTestCase
{
    private string $workDir;

    private string $lockPath;

    private Installer $originalInstaller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalInstaller = Container::get(Installer::class);
        $this->workDir = sys_get_temp_dir() . '/yd-rl53-' . bin2hex(random_bytes(4));
        mkdir($this->workDir, 0o755, true);
        $this->lockPath = $this->workDir . '/install.lock';
        file_put_contents($this->lockPath, date('c'));
        Container::set(Installer::class, new Installer(
            Container::get(SuperAdminInitializer::class),
            base_path() . '/database/install',
            $this->workDir . '/.env',
            base_path() . '/.env.example',
            $this->lockPath,
        ));
        Container::set(InstallController::class, Container::make(InstallController::class));
    }

    protected function tearDown(): void
    {
        Container::set(Installer::class, $this->originalInstaller);
        Container::set(InstallController::class, Container::make(InstallController::class));
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function test_lock_blocks_wizard_page_run_and_cli(): void
    {
        $page = $this->get('/install');
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('text/html', (string) $page->header('Content-Type'));
        $this->assertStringContainsString('已安装', $page->body());

        $run = $this->post('/install/run', $this->payload());
        $this->assertSame(200, $run->status());
        $this->assertSame(400, $run->code());
        $this->assertNotSame(401, $run->code());
        $this->assertSame(lang('install.already_installed'), $run->message());

        $tester = new CommandTester(new InstallCommand());
        $code = $tester->execute([
            '--db-name'  => 'yd_rl53',
            '--db-user'  => 'root',
            '--username' => 'admin',
            '--password' => 'Secret123',
        ], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString(lang('install.already_installed'), $tester->getDisplay());
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $mysql = (array) config('database.connections.mysql');
        $redis = (array) config('redis.default');

        return [
            'db_host'        => (string) $mysql['host'],
            'db_port'        => $mysql['port'],
            'db_name'        => (string) $mysql['database'],
            'db_user'        => (string) $mysql['username'],
            'db_password'    => (string) $mysql['password'],
            'redis_host'     => (string) $redis['host'],
            'redis_port'     => $redis['port'],
            'redis_password' => (string) ($redis['password'] ?? ''),
            'redis_db'       => $redis['database'],
            'username'       => 'admin',
            'password'       => 'Secret123',
        ];
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
