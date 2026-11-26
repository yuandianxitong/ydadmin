<?php

declare(strict_types=1);

namespace app\command;

use app\service\plugin\PluginService;
use core\exception\BusinessException;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('plugin:upgrade', '用 ZIP 包原位升级已启用的站点插件')]
final class PluginUpgradeCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('code', InputArgument::REQUIRED, '插件代码');
        $this->addArgument('zip', InputArgument::REQUIRED, '升级包路径');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = Container::get(PluginService::class)->upgrade(
                (string) $input->getArgument('code'),
                (string) $input->getArgument('zip'),
            );
        } catch (BusinessException $e) {
            $output->writeln($e->getMessage());
            return self::FAILURE;
        }

        $output->writeln(sprintf('%s 已升级至 %s', $result['code'], $result['version']));
        return self::SUCCESS;
    }
}
