<?php

declare(strict_types=1);

namespace tests\Unit\Database;

use core\database\DatabaseInstaller;
use core\database\DevDatabaseGuard;
use tests\TestCase;

final class DevDatabaseGuardTest extends TestCase
{
    private \PDO $pdo;

    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = DatabaseInstaller::connect((array) config('database.connections.mysql'));
        $this->scratch = (string) config('database.connections.mysql.database') . '_guard';
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DROP DATABASE IF EXISTS `{$this->scratch}`");
        parent::tearDown();
    }

    public function test_a_freshly_installed_database_is_not_stale(): void
    {
        // 测试库由 tests/bootstrap.php 按安装脚本指纹重建，必然是最新结构
        $this->assertSame([], DevDatabaseGuard::missing($this->pdo, (string) config('database.connections.mysql.database')));
    }

    public function test_missing_table_and_missing_column_are_both_reported(): void
    {
        $this->pdo->exec("DROP DATABASE IF EXISTS `{$this->scratch}`");
        $this->pdo->exec("CREATE DATABASE `{$this->scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $this->pdo->exec("USE `{$this->scratch}`");
        // 只建一张缺列的 system_configs：模拟停在 M1a 的开发库
        $this->pdo->exec('CREATE TABLE `system_configs` (`id` int unsigned NOT NULL AUTO_INCREMENT, `config_key` varchar(100) NOT NULL, PRIMARY KEY (`id`))');

        $missing = DevDatabaseGuard::missing($this->pdo, $this->scratch);

        $this->assertContains('system_configs 缺列 is_public', $missing);
        $this->assertContains('缺表 files', $missing);
        $this->assertContains('缺表 failed_jobs', $missing);
    }

    public function test_a_database_that_does_not_exist_is_not_reported_as_stale(): void
    {
        // 还没建库不算「过期」：新克隆的仓库只有测试库，不该因此跑不了 composer test
        $this->assertSame([], DevDatabaseGuard::missing($this->pdo, 'ydadmin_definitely_absent_db'));
    }
}
