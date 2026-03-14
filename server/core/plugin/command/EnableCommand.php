<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use core\plugin\PluginManager;

/**
 * 启用插件
 *
 * 用法: php think plugin:enable <name>
 */
class EnableCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:enable')
            ->setDescription('启用指定插件')
            ->addArgument('name', Argument::REQUIRED, '插件名称');
    }

    protected function execute(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');

        if (!PluginManager::isInstalled($name)) {
            $output->writeln(sprintf('<comment>插件 [%s] 尚未安装，请先安装</comment>', $name));
            return 1;
        }

        if (PluginManager::isEnabled($name)) {
            $output->writeln(sprintf('<comment>插件 [%s] 已处于启用状态</comment>', $name));
            return 1;
        }

        $output->writeln(sprintf('正在启用插件 <info>%s</info> ...', $name));

        $result = PluginManager::enable($name);

        if ($result) {
            $output->writeln(sprintf('<info>插件 [%s] 启用成功</info>', $name));
            return 0;
        }

        $output->writeln(sprintf('<error>插件 [%s] 启用失败，请查看日志获取详细信息</error>', $name));
        return 1;
    }
}
