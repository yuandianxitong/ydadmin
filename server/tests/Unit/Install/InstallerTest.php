<?php

declare(strict_types=1);

namespace tests\Unit\Install;

use core\contract\SuperAdminInitializer;
use core\database\DatabaseInstaller;
use core\exception\BusinessException;
use core\install\Installer;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use support\Db;
use tests\Support\ConfigOverride;
use tests\TestCase;
use Webman\Config;

final class InstallerTest extends TestCase
{
    use ConfigOverride;

    /** @var array<string, mixed> */
    private array $mysql;

    private string $scratch;

    private string $workDir;

    private string $lockPath;

    private string $envPath;

    private object $admins;

    private bool $stampedTestUpgrade = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mysql = (array) config('database.connections.mysql');
        $this->scratch = (string) $this->mysql['database'] . '_inst';
        $this->workDir = sys_get_temp_dir() . '/yd-inst-' . bin2hex(random_bytes(4));
        mkdir($this->workDir, 0o755, true);
        $this->lockPath = $this->workDir . '/install.lock';
        $this->envPath = $this->workDir . '/.env';
        file_put_contents($this->workDir . '/.env.example', "APP_DEBUG = true\nDB_HOST = 127.0.0.1\nKEEP_ME = 1\n");
        $this->writeMiniSql();
        $this->admins = new class () implements SuperAdminInitializer {
            public array $calls = [];

            public function initSuperAdmin(string $u, string $p, ?string $e, ?string $n): array
            {
                $this->calls[] = [$u, $p, $e, $n];

                return ['created' => true, 'id' => 1, 'username' => $u];
            }
        };
        $this->dropScratch();
    }

    protected function tearDown(): void
    {
        $this->restoreMysqlConnection();
        $this->dropScratch();
        if ($this->stampedTestUpgrade) {
            Db::table('system_upgrades')->where('version', '2.0.0')->delete();
            $this->stampedTestUpgrade = false;
        }
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function test_empty_database_writes_lock_version_and_jwt(): void
    {
        $installer = $this->makeInstaller();
        $installer->run($this->input());

        $this->assertFileExists($this->lockPath);
        $this->assertNotFalse(
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, trim((string) file_get_contents($this->lockPath))),
            'lock 内容必须是 ISO8601'
        );

        $pdo = DatabaseInstaller::connect($this->mysql);
        $pdo->exec('USE `' . $this->scratch . '`');
        $versions = $pdo->query('SELECT version FROM system_upgrades ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['2.0.0'], $versions);

        $env = (string) file_get_contents($this->envPath);
        $this->assertMatchesRegularExpression('/^JWT_ADMIN_SECRET\s*=\s*([0-9a-f]{64})$/m', $env);
        $this->assertMatchesRegularExpression('/^JWT_USER_SECRET\s*=\s*([0-9a-f]{64})$/m', $env);
        preg_match('/^JWT_ADMIN_SECRET\s*=\s*([0-9a-f]{64})$/m', $env, $adminJwt);
        preg_match('/^JWT_USER_SECRET\s*=\s*([0-9a-f]{64})$/m', $env, $userJwt);
        $this->assertNotSame($adminJwt[1], $userJwt[1]);
        $this->assertStringContainsString('APP_DEBUG = false', $env);
        $this->assertMatchesRegularExpression('/^DB_PREFIX\s*=\s*$/m', $env);

        $this->assertCount(1, $this->admins->calls);
        $this->assertSame(['admin', 'Secret123', 'admin@example.com', '超管'], $this->admins->calls[0]);

        $src = (string) file_get_contents(base_path() . '/core/install/Installer.php');
        $this->assertStringNotContainsString('reinstall', $src);
        $this->assertStringNotContainsString('DROP DATABASE', $src);
    }

    public function test_non_empty_database_is_rejected_without_lock(): void
    {
        $pdo = DatabaseInstaller::connect($this->mysql);
        $pdo->exec('CREATE DATABASE `' . $this->scratch . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $pdo->exec('USE `' . $this->scratch . '`');
        $pdo->exec('CREATE TABLE `foo` (`id` int unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))');

        try {
            $this->makeInstaller()->run($this->input());
            $this->fail('非空库必须拒绝安装');
        } catch (BusinessException $e) {
            $this->assertSame(lang('install.database_not_empty'), $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->lockPath);
        $this->assertFileDoesNotExist($this->envPath);
        $pdo->exec('USE `' . $this->scratch . '`');
        $this->assertNotFalse($pdo->query("SHOW TABLES LIKE 'foo'")->fetch(), '拒绝安装不得 DROP 已有库');
    }

    public function test_sql_failure_does_not_write_env_or_lock(): void
    {
        file_put_contents($this->workDir . '/schema.sql', 'THIS IS NOT SQL;');

        try {
            $this->makeInstaller()->run($this->input());
            $this->fail('非法 SQL 必须失败');
        } catch (BusinessException $e) {
            $this->assertSame(lang('install.sql_failed'), $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->lockPath);
        $this->assertFileDoesNotExist($this->envPath);
    }

    public function test_lock_file_means_installed(): void
    {
        file_put_contents($this->lockPath, date('c'));
        $installer = $this->makeInstaller();

        $this->assertTrue($installer->isInstalled());
        try {
            $installer->run($this->input());
            $this->fail('已有 lock 不得再次安装');
        } catch (BusinessException $e) {
            $this->assertSame(lang('install.already_installed'), $e->getMessage());
        }
    }

    public function test_upgrades_row_means_installed(): void
    {
        Db::table('system_upgrades')->insert(['version' => '2.0.0', 'applied_at' => date('Y-m-d H:i:s')]);
        $this->stampedTestUpgrade = true;

        $installer = $this->makeInstaller();
        $this->assertFalse(is_file($this->lockPath));
        $this->assertTrue($installer->isInstalled(), 'isInstalled 必须读当前 config() 的测试库，而不是 scratch');
    }

    public function test_missing_upgrades_table_is_not_installed(): void
    {
        $pdo = DatabaseInstaller::connect($this->mysql);
        $pdo->exec('CREATE DATABASE `' . $this->scratch . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        $mysql = $this->mysql;
        $mysql['database'] = $this->scratch;
        $this->overrideConfig('database.connections.mysql', $mysql);

        $this->assertFalse(is_file($this->lockPath));
        $this->assertFalse($this->makeInstaller()->isInstalled());
    }

    public function test_test_database_selects_one(): void
    {
        $this->makeInstaller()->testDatabase($this->input(['db_name' => (string) $this->mysql['database']]));
        $this->addToAssertionCount(1);
    }

    public function test_test_redis_pings(): void
    {
        $this->makeInstaller()->testRedis($this->input());
        $this->addToAssertionCount(1);
    }

    private function makeInstaller(): Installer
    {
        return new Installer(
            $this->admins,
            $this->workDir,
            $this->envPath,
            $this->workDir . '/.env.example',
            $this->lockPath,
        );
    }

    /** @param array<string, mixed> $overrides */
    private function input(array $overrides = []): array
    {
        $redis = (array) config('redis.default');

        return array_replace([
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
            'email'          => 'admin@example.com',
            'nickname'       => '超管',
        ], $overrides);
    }

    private function writeMiniSql(): void
    {
        file_put_contents($this->workDir . '/schema.sql', <<<'SQL'
CREATE TABLE `ping` (`id` int unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`));
CREATE TABLE `system_upgrades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(32) NOT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_version` (`version`)
);
CREATE TABLE `admins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
);
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `is_system` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
);
CREATE TABLE `admin_roles` (`admin_id` int unsigned NOT NULL, `role_id` int unsigned NOT NULL, PRIMARY KEY (`admin_id`,`role_id`));
SQL);
        file_put_contents($this->workDir . '/init.sql', "INSERT INTO `roles` (`id`,`name`,`is_system`) VALUES (1,'super_admin',1);\n");
        file_put_contents($this->workDir . '/regions.sql', "--\n");
    }

    private function dropScratch(): void
    {
        DatabaseInstaller::connect($this->mysql)->exec('DROP DATABASE IF EXISTS `' . $this->scratch . '`');
    }

    private function restoreMysqlConnection(): void
    {
        $this->restoreConfig();
        $property = new \ReflectionProperty(Config::class, 'config');
        /** @var array<string, mixed> $all */
        $all = $property->getValue();
        $all['database']['connections']['mysql'] = $this->mysql;
        $property->setValue(null, $all);
        (new \ReflectionProperty(Config::class, 'flatCache'))->setValue(null, []);

        $capsule = (new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class, 'instance'))->getValue();
        if ($capsule instanceof \Illuminate\Database\Capsule\Manager) {
            $capsule->addConnection($this->mysql, 'mysql');
            $capsule->getDatabaseManager()->purge('mysql');
        } else {
            $resolver = Model::getConnectionResolver();
            if ($resolver instanceof \Illuminate\Database\DatabaseManager) {
                $resolver->purge('mysql');
            }
        }
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
