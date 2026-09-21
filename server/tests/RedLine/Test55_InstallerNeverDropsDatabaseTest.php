<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\contract\SuperAdminInitializer;
use core\database\DatabaseInstaller;
use core\exception\BusinessException;
use core\install\Installer;
use tests\TestCase;

/**
 * 红线 Test55：安装 / 升级内核源码不得含 reinstall 或 DROP DATABASE；非空库被拒后库名仍在。
 *
 * 会让本用例失败的生产改动：Installer/Upgrader 调用 DatabaseInstaller::reinstall、拼 DROP DATABASE、
 * 或拒绝非空库时把整个 schema 删掉。
 */
final class Test55_InstallerNeverDropsDatabaseTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $mysql;

    private string $scratch;

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mysql = (array) config('database.connections.mysql');
        $this->scratch = (string) $this->mysql['database'] . '_inst';
        $this->assertNotSame('dev007_ydadmin', $this->scratch);
        $this->workDir = sys_get_temp_dir() . '/yd-rl55-' . bin2hex(random_bytes(4));
        mkdir($this->workDir, 0o755, true);
        $this->dropScratch();
    }

    protected function tearDown(): void
    {
        $this->dropScratch();
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function test_source_has_no_reinstall_or_drop_and_nonempty_scratch_survives_run(): void
    {
        foreach ([
            base_path() . '/core/install/Installer.php',
            base_path() . '/core/install/Upgrader.php',
        ] as $path) {
            $src = (string) file_get_contents($path);
            $this->assertStringNotContainsString('reinstall', $src, $path);
            $this->assertDoesNotMatchRegularExpression('/drop\s+database/i', $src, $path);
        }

        $pdo = DatabaseInstaller::connect($this->mysql);
        $pdo->exec('CREATE DATABASE `' . $this->scratch . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $pdo->exec('USE `' . $this->scratch . '`');
        $pdo->exec('CREATE TABLE `keep_me` (`id` int unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))');

        try {
            $this->makeInstaller()->run($this->input());
            $this->fail('非空 scratch 必须拒绝安装');
        } catch (BusinessException $e) {
            $this->assertSame(lang('install.database_not_empty'), $e->getMessage());
        }

        $names = $pdo->query('SHOW DATABASES')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains($this->scratch, $names);
    }

    private function makeInstaller(): Installer
    {
        $admins = new class () implements SuperAdminInitializer {
            public function initSuperAdmin(string $u, string $p, ?string $e, ?string $n): array
            {
                return ['created' => true, 'id' => 1, 'username' => $u];
            }
        };

        return new Installer(
            $admins,
            $this->workDir,
            $this->workDir . '/.env',
            $this->workDir . '/.env.example',
            $this->workDir . '/install.lock',
        );
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $redis = (array) config('redis.default');

        return [
            'db_host'        => (string) $this->mysql['host'],
            'db_port'        => $this->mysql['port'],
            'db_name'        => $this->scratch,
            'db_user'        => (string) $this->mysql['username'],
            'db_password'    => (string) $this->mysql['password'],
            'redis_host'     => (string) $redis['host'],
            'redis_port'     => $redis['port'],
            'redis_password' => (string) ($redis['password'] ?? ''),
            'redis_db'       => $redis['database'],
            'username'       => 'admin',
            'password'       => 'Secret123',
        ];
    }

    private function dropScratch(): void
    {
        DatabaseInstaller::connect($this->mysql)->exec('DROP DATABASE IF EXISTS `' . $this->scratch . '`');
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
