<?php

declare(strict_types=1);

namespace tests\Unit\Command;

use app\command\DbResetCommand;
use core\database\DatabaseInstaller;
use DateTimeImmutable;
use DateTimeInterface;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\TestCase;

final class DbResetCommandTest extends TestCase
{
    public function test_refuses_to_run_without_debug(): void
    {
        $tester = new CommandTester(new DbResetCommand(debug: false));

        $this->assertSame(Command::FAILURE, $tester->execute(['--force' => true]));
        $this->assertStringContainsString('APP_DEBUG', $tester->getDisplay());
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $tester = new CommandTester(new DbResetCommand(debug: true));
        $tester->setInputs(['n']);

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('已取消', $tester->getDisplay());
        $this->assertSame(1, Db::table('roles')->where('id', 1)->count(), '拒绝确认后数据库不得被重建');
    }

    public function test_success_stamps_baseline_version_and_lock(): void
    {
        $mysql = (array) config('database.connections.mysql');
        $scratch = (string) $mysql['database'] . '_reset';
        $dir = sys_get_temp_dir() . '/yd-reset-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        $lock = $dir . '/install.lock';
        file_put_contents($dir . '/schema.sql', <<<'SQL'
CREATE TABLE `system_upgrades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(32) NOT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_version` (`version`)
);
CREATE TABLE `ping` (`id` int unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`));
SQL);
        file_put_contents($dir . '/init.sql', "--\n");
        file_put_contents($dir . '/regions.sql', "--\n");

        $pdo = DatabaseInstaller::connect($mysql);
        $pdo->exec('DROP DATABASE IF EXISTS `' . $scratch . '`');
        try {
            $tester = new CommandTester(new DbResetCommand(
                debug: true,
                lockPath: $lock,
                installDir: $dir,
                database: $scratch,
            ));
            $this->assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));
            $this->assertStringContainsString('lock 已写', $tester->getDisplay());
            $this->assertStringContainsString('admin:init', $tester->getDisplay());
            $this->assertFileExists($lock);
            $this->assertNotFalse(
                DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, trim((string) file_get_contents($lock)))
            );
            $pdo->exec('USE `' . $scratch . '`');
            $this->assertSame(['2.0.0'], $pdo->query('SELECT version FROM system_upgrades')->fetchAll(\PDO::FETCH_COLUMN));
        } finally {
            $pdo->exec('DROP DATABASE IF EXISTS `' . $scratch . '`');
            foreach (['schema.sql', 'init.sql', 'regions.sql', 'install.lock'] as $file) {
                $path = $dir . '/' . $file;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($dir);
        }
    }
}
