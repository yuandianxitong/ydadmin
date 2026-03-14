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
use core\plugin\PluginManager;

/**
 * 列出所有可用插件
 *
 * 用法: php think plugin:list
 */
class ListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:list')
            ->setDescription('列出所有可用插件');
    }

    protected function execute(Input $input, Output $output): int
    {
        $plugins = PluginManager::scanAvailablePlugins();

        if (empty($plugins)) {
            $output->writeln('<info>暂无可用插件</info>');
            return 0;
        }

        // 构建表格数据
        $rows = [];

        foreach ($plugins as $name => $info) {
            if ($info['installed'] && $info['enabled']) {
                $status = '<info>已启用</info>';
            } elseif ($info['installed']) {
                $status = '<comment>已禁用</comment>';
            } else {
                $status = '未安装';
            }

            $rows[] = [
                $name,
                $info['version'] ?? '-',
                $status,
                $info['description'] ?? '-',
            ];
        }

        $output->writeln(str_pad('插件名称', 24) . str_pad('版本', 12) . str_pad('状态', 20) . '描述');
        $output->writeln(str_repeat('-', 80));
        foreach ($rows as $row) {
            $output->writeln(str_pad($row[0], 20) . str_pad($row[1], 12) . str_pad($row[2], 24) . $row[3]);
        }

        $output->writeln('');
        $output->writeln(sprintf('共 <info>%d</info> 个插件', count($plugins)));

        return 0;
    }
}
