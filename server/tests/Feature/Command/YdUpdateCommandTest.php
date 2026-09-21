<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\YdUpdateCommand;
use core\database\DatabaseInstaller;
use core\install\Upgrader;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\TestCase;

final class YdUpdateCommandTest extends TestCase
{
    public function test_dry_run_does_not_change_system_upgrades_count(): void
    {
        $seeded = false;
        if ((int) Db::table('system_upgrades')->count() === 0) {
            Db::table('system_upgrades')->insert([
                'version'    => '2.0.0',
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
            $seeded = true;
        }
        try {
            $before = (int) Db::table('system_upgrades')->count();
            $tester = new CommandTester(new YdUpdateCommand());
            $code = $tester->execute(['--dry-run' => true]);

            $this->assertSame(Command::SUCCESS, $code, $tester->getDisplay());
            $this->assertStringContainsString((string) config('version.version'), $tester->getDisplay());
            $this->assertSame($before, (int) Db::table('system_upgrades')->count());
        } finally {
            if ($seeded) {
                Db::table('system_upgrades')->where('version', '2.0.0')->delete();
            }
        }
    }

    public function test_empty_table_without_baseline_fails(): void
    {
        $backup = Db::table('system_upgrades')->get(['version', 'applied_at'])->all();
        try {
            Db::table('system_upgrades')->delete();
            $tester = new CommandTester(new YdUpdateCommand());
            $code = $tester->execute([]);

            $this->assertSame(Command::FAILURE, $code);
            $this->assertStringContainsString(lang('install.baseline_required'), $tester->getDisplay());
        } finally {
            Db::table('system_upgrades')->delete();
            foreach ($backup as $row) {
                Db::table('system_upgrades')->insert([
                    'version'    => $row->version,
                    'applied_at' => $row->applied_at,
                ]);
            }
        }
    }

    public function test_injected_upgrader_receives_dry_run_and_baseline(): void
    {
        $probe = new class () {
            /** @var list<array{0: \PDO, 1: ?string, 2: bool}> */
            public array $calls = [];
        };
        $fake = new class ($probe) {
            public function __construct(private object $probe)
            {
            }

            /**
             * @return array{stamped: list<string>, executed: list<string>, pending: list<string>}
             */
            public function run(\PDO $pdo, ?string $baseline, bool $dryRun): array
            {
                $this->probe->calls[] = [$pdo, $baseline, $dryRun];

                return ['stamped' => ['2.0.0'], 'executed' => [], 'pending' => []];
            }
        };

        $mysql = (array) config('database.connections.mysql');
        $pdo = DatabaseInstaller::connect($mysql);
        $pdo->exec('USE `' . (string) $mysql['database'] . '`');
        $before = (int) $pdo->query('SELECT COUNT(*) FROM system_upgrades')->fetchColumn();

        $tester = new CommandTester(new YdUpdateCommand($fake, $pdo));
        $code = $tester->execute(['--dry-run' => true, '--baseline' => '2.0.0']);

        $this->assertSame(Command::SUCCESS, $code, $tester->getDisplay());
        $this->assertStringContainsString((string) config('version.version'), $tester->getDisplay());
        $this->assertCount(1, $probe->calls);
        $this->assertSame($pdo, $probe->calls[0][0]);
        $this->assertSame('2.0.0', $probe->calls[0][1]);
        $this->assertTrue($probe->calls[0][2]);
        $this->assertSame($before, (int) $pdo->query('SELECT COUNT(*) FROM system_upgrades')->fetchColumn());
    }

    public function test_throwable_from_upgrader_is_printed(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $tester = new CommandTester(new YdUpdateCommand(
            new Upgrader(base_path() . '/database/updates'),
            $pdo
        ));
        $code = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertNotSame('', trim($tester->getDisplay()));
    }
}
