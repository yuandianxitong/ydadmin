<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\InstallCommand;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\install\Installer;
use support\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\TestCase;

final class InstallCommandTest extends TestCase
{
    private Installer $originalInstaller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalInstaller = Container::get(Installer::class);
    }

    protected function tearDown(): void
    {
        Container::set(Installer::class, $this->originalInstaller);
        parent::tearDown();
    }

    public function test_no_interaction_without_username_fails(): void
    {
        $this->installFake();

        $tester = new CommandTester(new InstallCommand());
        $code = $tester->execute([
            '--db-name'  => 'yd_app',
            '--db-user'  => 'root',
            '--password' => 'Secret123',
        ], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $code);
    }

    public function test_all_options_succeed_and_mention_restart(): void
    {
        $fake = $this->installFake();

        $tester = new CommandTester(new InstallCommand());
        $code = $tester->execute($this->allOptions(), ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $code, $tester->getDisplay());
        $this->assertStringContainsString('restart', $tester->getDisplay());
        $this->assertSame([
            'db_host'        => '127.0.0.1',
            'db_port'        => 3306,
            'db_name'        => 'yd_app',
            'db_user'        => 'root',
            'db_password'    => 's3cret',
            'redis_host'     => '127.0.0.1',
            'redis_port'     => 6379,
            'redis_password' => 'rpass',
            'redis_db'       => 2,
            'username'       => 'admin',
            'password'       => 'Secret123',
            'email'          => 'admin@example.com',
            'nickname'       => '站长',
        ], $fake->input);
    }

    public function test_already_installed_prints_business_message(): void
    {
        $this->installFake(static function (): void {
            throw new BusinessException(lang('install.already_installed'));
        });

        $tester = new CommandTester(new InstallCommand());
        $code = $tester->execute($this->allOptions(), ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString(lang('install.already_installed'), $tester->getDisplay());
    }

    public function test_validation_errors_print_each_field(): void
    {
        $this->installFake(static function (): void {
            throw new ValidationException(['username' => '太短', 'email' => '无效']);
        });

        $tester = new CommandTester(new InstallCommand());
        $code = $tester->execute($this->allOptions(), ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $code);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('username', $display);
        $this->assertStringContainsString('太短', $display);
        $this->assertStringContainsString('email', $display);
        $this->assertStringContainsString('无效', $display);
    }

    public function test_throwable_is_printed(): void
    {
        $this->installFake(static function (): void {
            throw new \RuntimeException('installer exploded');
        });

        $tester = new CommandTester(new InstallCommand());
        $code = $tester->execute($this->allOptions(), ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('installer exploded', $tester->getDisplay());
    }

    /** @return array<string, string> */
    private function allOptions(): array
    {
        return [
            '--db-host'        => '127.0.0.1',
            '--db-port'        => '3306',
            '--db-name'        => 'yd_app',
            '--db-user'        => 'root',
            '--db-password'    => 's3cret',
            '--redis-host'     => '127.0.0.1',
            '--redis-port'     => '6379',
            '--redis-password' => 'rpass',
            '--redis-db'       => '2',
            '--username'       => 'admin',
            '--password'       => 'Secret123',
            '--email'          => 'admin@example.com',
            '--nickname'       => '站长',
        ];
    }

    /** @param (callable(): void)|null $run */
    private function installFake(?callable $run = null): object
    {
        $fake = new class ($run) {
            /** @var array<string, mixed>|null */
            public ?array $input = null;

            /** @param (callable(): void)|null $run */
            public function __construct(private readonly mixed $run)
            {
            }

            /** @param array<string, mixed> $input */
            public function run(array $input): void
            {
                $this->input = $input;
                if ($this->run !== null) {
                    ($this->run)();
                }
            }
        };
        Container::set(Installer::class, $fake);

        return $fake;
    }
}
