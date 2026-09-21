<?php

declare(strict_types=1);

namespace tests\Unit\Install;

use core\database\DatabaseInstaller;
use core\exception\BusinessException;
use core\install\Upgrader;
use tests\TestCase;

final class UpgraderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $mysql;

    private string $scratch;

    private string $updatesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mysql = (array) config('database.connections.mysql');
        $this->scratch = (string) $this->mysql['database'] . '_upg';
        $this->assertNotSame('dev007_ydadmin', $this->scratch);
        $this->updatesDir = sys_get_temp_dir() . '/yd-upg-' . bin2hex(random_bytes(4));
        mkdir($this->updatesDir, 0o755, true);
        $this->dropScratch();
        $this->createScratch();
    }

    protected function tearDown(): void
    {
        $this->dropScratch();
        $this->removeDir($this->updatesDir);
        parent::tearDown();
    }

    public function test_empty_applied_without_baseline_fails(): void
    {
        try {
            (new Upgrader($this->updatesDir))->run($this->scratchPdo(), null, false);
            $this->fail('无升级记录且无 baseline 必须失败');
        } catch (BusinessException $e) {
            $this->assertSame(lang('install.baseline_required'), $e->getMessage());
        }

        $this->assertFalse($this->tableExists('system_upgrades'));
    }

    public function test_baseline_stamps_itself_without_directory(): void
    {
        $result = (new Upgrader($this->updatesDir))->run($this->scratchPdo(), '2.0.0', false);

        $this->assertSame(['2.0.0'], $result['stamped']);
        $this->assertSame([], $result['executed']);
        $this->assertSame(['2.0.0'], $this->versions());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->writeVersion('v2.0.1', 'CREATE TABLE t (id int);', null);

        $result = (new Upgrader($this->updatesDir))->run($this->scratchPdo(), '2.0.0', true);

        $this->assertContains('2.0.0', $result['stamped']);
        $this->assertContains('2.0.1', $result['pending']);
        $this->assertSame([], $result['executed']);
        $this->assertFalse($this->tableExists('system_upgrades'));
        $this->assertFalse($this->tableExists('t'));
    }

    public function test_executes_pending_after_baseline(): void
    {
        $this->writeVersion('v2.0.1', 'CREATE TABLE t (id int);', <<<'PHP'
<?php
return function (\PDO $pdo): void {
    $pdo->exec('CREATE TABLE t_php (id int)');
};
PHP);

        $pdo = $this->scratchPdo();
        $upgrader = new Upgrader($this->updatesDir);

        $first = $upgrader->run($pdo, '2.0.0', false);
        $this->assertSame(['2.0.0'], $first['stamped']);
        $this->assertSame([], $first['executed']);
        $this->assertSame(['2.0.0'], $this->versions($pdo));
        $this->assertFalse($this->tableExists('t', $pdo));
        $this->assertFalse($this->tableExists('t_php', $pdo));

        $second = $upgrader->run($pdo, null, false);
        $this->assertContains('2.0.1', $second['executed']);
        $this->assertSame([], $second['pending']);
        $this->assertNotFalse($pdo->query("SHOW TABLES LIKE 't'")->fetch());
        $this->assertNotFalse($pdo->query("SHOW TABLES LIKE 't_php'")->fetch());
        $this->assertSame(['2.0.0', '2.0.1'], $this->versions($pdo));
    }

    public function test_failed_version_can_resume(): void
    {
        $this->writeVersion('v2.0.1', 'THIS IS NOT SQL;', null);
        $pdo = $this->scratchPdo();
        $upgrader = new Upgrader($this->updatesDir);
        $upgrader->run($pdo, '2.0.0', false);

        try {
            $upgrader->run($pdo, null, false);
            $this->fail('非法 SQL 必须失败');
        } catch (\Throwable) {
        }

        $this->assertSame(['2.0.0'], $this->versions($pdo));
        $this->assertFalse($this->tableExists('t_resume', $pdo));

        file_put_contents($this->updatesDir . '/v2.0.1/update.sql', 'CREATE TABLE t_resume (id int);');
        $upgrader->run($pdo, null, false);

        $this->assertSame(['2.0.0', '2.0.1'], $this->versions($pdo));
        $this->assertNotFalse($pdo->query("SHOW TABLES LIKE 't_resume'")->fetch());
    }

    public function test_rejects_path_escape(): void
    {
        $evilMarker = $this->updatesDir . '/evil-loaded';
        $otherMarker = $this->updatesDir . '/other-loaded';
        $this->writeVersion('v2.0.1-evil', null, $this->markerPhp($evilMarker));
        mkdir($this->updatesDir . '/not-a-version', 0o755, true);
        file_put_contents($this->updatesDir . '/not-a-version/update.php', $this->markerPhp($otherMarker));

        $pdo = $this->scratchPdo();
        $result = (new Upgrader($this->updatesDir))->run($pdo, '2.0.0', false);

        $this->assertSame(['2.0.0'], $result['stamped']);
        $this->assertFileDoesNotExist($evilMarker);
        $this->assertFileDoesNotExist($otherMarker);

        $outside = $this->updatesDir . '/../outside-update.php';
        $symlinkMarker = $this->updatesDir . '/symlink-loaded';
        file_put_contents($outside, $this->markerPhp($symlinkMarker));
        mkdir($this->updatesDir . '/v2.0.1', 0o755, true);
        if (@symlink($outside, $this->updatesDir . '/v2.0.1/update.php')) {
            try {
                (new Upgrader($this->updatesDir))->run($pdo, null, false);
                $this->fail('逃逸路径的 update.php 必须拒绝加载');
            } catch (BusinessException $e) {
                $this->assertSame(lang('install.invalid_update_dir'), $e->getMessage());
            }
            $this->assertFileDoesNotExist($symlinkMarker);
            $this->assertSame(['2.0.0'], $this->versions($pdo));
        }
    }

    public function test_already_latest_when_applied_and_no_dirs(): void
    {
        $pdo = $this->scratchPdo();
        $pdo->exec(<<<'SQL'
CREATE TABLE `system_upgrades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(32) NOT NULL COMMENT '已应用版本，如 2.0.0',
  `applied_at` datetime NOT NULL COMMENT '打标时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='框架升级记录'
SQL);
        $pdo->exec("INSERT INTO system_upgrades (version, applied_at) VALUES ('2.0.0', NOW())");

        $result = (new Upgrader($this->updatesDir))->run($pdo, null, false);

        $this->assertSame([], $result['pending']);
        $this->assertSame([], $result['executed']);
        $this->assertSame(['2.0.0'], $this->versions($pdo));
    }

    private function scratchPdo(): \PDO
    {
        $pdo = DatabaseInstaller::connect($this->mysql);
        $pdo->exec('USE `' . $this->scratch . '`');

        return $pdo;
    }

    private function createScratch(): void
    {
        DatabaseInstaller::connect($this->mysql)->exec(
            'CREATE DATABASE `' . $this->scratch . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci'
        );
    }

    private function dropScratch(): void
    {
        DatabaseInstaller::connect($this->mysql)->exec('DROP DATABASE IF EXISTS `' . $this->scratch . '`');
    }

    private function tableExists(string $table, ?\PDO $pdo = null): bool
    {
        $pdo ??= $this->scratchPdo();

        return $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetch() !== false;
    }

    /** @return list<string> */
    private function versions(?\PDO $pdo = null): array
    {
        $pdo ??= $this->scratchPdo();

        return $pdo->query('SELECT version FROM system_upgrades ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function writeVersion(string $dirName, ?string $sql, ?string $php): void
    {
        $dir = $this->updatesDir . '/' . $dirName;
        mkdir($dir, 0o755, true);
        if ($sql !== null) {
            file_put_contents($dir . '/update.sql', $sql);
        }
        if ($php !== null) {
            file_put_contents($dir . '/update.php', $php);
        }
    }

    private function markerPhp(string $marker): string
    {
        return '<?php file_put_contents(' . var_export($marker, true) . ", 'loaded');\n"
            . "return function (\\PDO \$pdo): void {};\n";
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
        $outside = dirname($this->updatesDir) . '/outside-update.php';
        if (is_file($outside)) {
            unlink($outside);
        }
    }
}
