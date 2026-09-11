<?php

declare(strict_types=1);

namespace tests\Unit\Command;

use app\command\DbResetCommand;
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
}
