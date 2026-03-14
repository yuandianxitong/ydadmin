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
 * 安装插件
 *
 * 用法: php think plugin:install <name>
 */
class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:install')
            ->setDescription('安装指定插件')
            ->addArgument('name', Argument::REQUIRED, '插件名称');
    }

    protected function execute(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');

        if (PluginManager::isInstalled($name)) {
            $output->writeln(sprintf('<comment>插件 [%s] 已安装，无需重复安装</comment>', $name));
            return 1;
        }

        $output->writeln(sprintf('正在安装插件 <info>%s</info> ...', $name));

        $result = PluginManager::install($name);

        if ($result) {
            $output->writeln(sprintf('<info>插件 [%s] 安装成功</info>', $name));
            return 0;
        }

        $output->writeln(sprintf('<error>插件 [%s] 安装失败，请查看日志获取详细信息</error>', $name));
        return 1;
    }
}
