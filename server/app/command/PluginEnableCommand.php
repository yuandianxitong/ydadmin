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

/** 命令只做参数解析与输出，逻辑在 PluginService::enable()；BusinessException 交给命令运行器。 */
#[AsCommand('plugin:enable', '启用已禁用的插件（站点级）')]
final class PluginEnableCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('code', InputArgument::REQUIRED, '插件 code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = Container::get(PluginService::class)->enable((string) $input->getArgument('code'));
        $output->writeln($result['code'] . ' ' . $result['status']);

        return self::SUCCESS;
    }
}
