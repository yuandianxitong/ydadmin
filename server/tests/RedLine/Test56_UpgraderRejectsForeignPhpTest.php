<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\database\DatabaseInstaller;
use core\install\Upgrader;
use tests\TestCase;

/**
 * 红线 Test56：升级器只加载 vX.Y.Z/update.php，根目录散落的 PHP 与路径穿越目录名都不得被 include。
 *
 * 会让本用例失败的生产改动：glob 整个 updatesDir 的 *.php、或把 `v2.0.1/../../tmp` 当版本目录 realpath。
 */
final class Test56_UpgraderRejectsForeignPhpTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $mysql;

    private string $scratch;

    private string $workDir;

    private string $updatesDir;

    private string $evilMarker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mysql = (array) config('database.connections.mysql');
        $this->scratch = (string) $this->mysql['database'] . '_rl56';
        $this->assertNotSame('dev007_ydadmin', $this->scratch);
        $this->workDir = sys_get_temp_dir() . '/yd-rl56-' . bin2hex(random_bytes(4));
        $this->updatesDir = $this->workDir . '/updates';
        mkdir($this->updatesDir, 0o755, true);
        $this->evilMarker = $this->updatesDir . '/evil-loaded';
        $this->dropScratch();
        DatabaseInstaller::connect($this->mysql)->exec(
            'CREATE DATABASE `' . $this->scratch . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci'
        );
    }

    protected function tearDown(): void
    {
        $this->dropScratch();
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function test_evil_php_and_traversal_dir_name_are_not_loaded(): void
    {
        file_put_contents($this->updatesDir . '/evil.php', $this->markerPhp($this->evilMarker));
        mkdir($this->updatesDir . '/v2.0.1', 0o755, true);
        file_put_contents($this->updatesDir . '/v2.0.1/update.php', <<<'PHP'
<?php
return function (\PDO $pdo): void {
    $pdo->exec('CREATE TABLE t_ok (id int)');
};
PHP);

        $pdo = $this->scratchPdo();
        $upgrader = new Upgrader($this->updatesDir);
        $upgrader->run($pdo, '2.0.0', false);
        $upgrader->run($pdo, null, false);

        $this->assertFileDoesNotExist($this->evilMarker);
        $this->assertNotFalse($pdo->query("SHOW TABLES LIKE 't_ok'")->fetch());

        $escapeMarker = $this->updatesDir . '/escape-loaded';
        $escapeDir = $this->updatesDir . '/v2.0.1/../../tmp';
        mkdir($escapeDir, 0o755, true);
        file_put_contents($escapeDir . '/update.php', $this->markerPhp($escapeMarker));

        (new Upgrader($this->updatesDir))->run($pdo, null, false);

        $this->assertFileDoesNotExist($escapeMarker);
        $this->assertFileDoesNotExist($this->evilMarker);
        $versions = $pdo->query('SELECT version FROM system_upgrades ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['2.0.0', '2.0.1'], $versions);
    }

    private function scratchPdo(): \PDO
    {
        $pdo = DatabaseInstaller::connect($this->mysql);
        $pdo->exec('USE `' . $this->scratch . '`');

        return $pdo;
    }

    private function dropScratch(): void
    {
        DatabaseInstaller::connect($this->mysql)->exec('DROP DATABASE IF EXISTS `' . $this->scratch . '`');
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
    }
}
