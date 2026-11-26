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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 卸载插件。只转调 PluginService，不重启 worker。只有显式 --purge 才传 purge: true（执行 database/uninstall.sql 删数据，
 * 且要求插件已停用）。后台清除数据走 plugin.purge，同样要求先停用。不进定时任务白名单（config/cron.php）。
 */
#[AsCommand('plugin:uninstall', '卸载站点插件（保留 server/plugins/<code> 目录；--purge 需先停用，并执行 uninstall.sql）')]
final class PluginUninstallCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('code', InputArgument::REQUIRED, '插件 code');
        $this->addOption('purge', null, InputOption::VALUE_NONE, '执行database/uninstall.sql，清掉插件数据（不可恢复）');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $purge = (bool) $input->getOption('purge');
        try {
            $result = Container::get(PluginService::class)->uninstall((string) $input->getArgument('code'), $purge);
        } catch (BusinessException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>插件 %s 已卸载%s。目录 server/plugins/%s 仍保留。</info>',
            $result['code'],
            $purge ? '，数据已清除' : '，数据已保留',
            $result['code']
        ));

        return self::SUCCESS;
    }
}
