<?php

declare(strict_types=1);

namespace app\command;

use app\service\plugin\PluginService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** 命令只做参数解析与输出，逻辑在 PluginService::disable()；BusinessException 交给命令运行器。 */
#[AsCommand('plugin:disable', '禁用插件（站点级）')]
final class PluginDisableCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('code', InputArgument::REQUIRED, '插件 code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = Container::get(PluginService::class)->disable((string) $input->getArgument('code'));
        $output->writeln($result['code'] . ' ' . $result['status']);

        return self::SUCCESS;
    }
}
