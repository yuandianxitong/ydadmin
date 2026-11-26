<?php

declare(strict_types=1);

namespace tests\Feature\Plugin;

use app\command\PluginDisableCommand;
use app\command\PluginEnableCommand;
use app\command\PluginInstallCommand;
use app\command\PluginUninstallCommand;
use app\service\plugin\PluginService;
use PHPUnit\Framework\Attributes\DataProvider;
use support\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\TestCase;

final class PluginCommandTest extends TestCase
{
    private PluginService $original;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original = Container::make(PluginService::class);
    }

    protected function tearDown(): void
    {
        Container::set(PluginService::class, $this->original);
        parent::tearDown();
    }

    /** @return array<string, array{class-string<Command>, string, string}> */
    public static function commands(): array
    {
        return [
            'install' => [PluginInstallCommand::class, 'plugin:install', 'install'],
            'enable' => [PluginEnableCommand::class, 'plugin:enable', 'enable'],
            'disable' => [PluginDisableCommand::class, 'plugin:disable', 'disable'],
        ];
    }

    #[DataProvider('commands')]
    public function test_each_command_forwards_the_code_to_the_matching_service_method(
        string $commandClass,
        string $name,
        string $method,
    ): void {
        $fake = new class () extends PluginService {
            /** @var list<array{string, string}> */
            public array $calls = [];

            public function __construct()
            {
            }

            public function install(string $code): array
            {
                $this->calls[] = ['install', $code];

                return ['id' => 1, 'code' => $code, 'version' => '1.0.0', 'status' => 1];
            }

            public function enable(string $code): array
            {
                $this->calls[] = ['enable', $code];

                return ['id' => 1, 'code' => $code, 'status' => 1];
            }

            public function disable(string $code): array
            {
                $this->calls[] = ['disable', $code];

                return ['id' => 1, 'code' => $code, 'status' => 2];
            }
        };
        Container::set(PluginService::class, $fake);

        $command = Container::make($commandClass);
        $this->assertSame($name, $command->getName());
        if ($name === 'plugin:install') {
            $this->assertStringContainsString('server/plugins', $command->getDescription());
            $this->assertStringContainsString('plugin:create', $command->getDescription());
        }

        $tester = new CommandTester($command);
        $exit = $tester->execute(['code' => 'probe']);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertSame([[$method, 'probe']], $fake->calls);
        $this->assertStringContainsString('probe', $tester->getDisplay());
    }

    public function test_uninstall_success_says_the_directory_stays(): void
    {
        $fake = new class () extends PluginService {
            public function __construct()
            {
            }

            public function uninstall(string $code, bool $purge = false): array
            {
                return ['code' => $code, 'status' => 'uninstalled'];
            }
        };
        Container::set(PluginService::class, $fake);

        $kept = new CommandTester(Container::make(PluginUninstallCommand::class));
        $this->assertSame(Command::SUCCESS, $kept->execute(['code' => 'probe']));
        $keptDisplay = $kept->getDisplay();
        $this->assertStringContainsString('数据已保留', $keptDisplay);
        $this->assertStringContainsString('目录 server/plugins/probe 仍保留', $keptDisplay);

        $purged = new CommandTester(Container::make(PluginUninstallCommand::class));
        $this->assertSame(Command::SUCCESS, $purged->execute(['code' => 'probe', '--purge' => true]));
        $purgedDisplay = $purged->getDisplay();
        $this->assertStringContainsString('数据已清除', $purgedDisplay);
        $this->assertStringContainsString('目录 server/plugins/probe 仍保留', $purgedDisplay);
    }
}
