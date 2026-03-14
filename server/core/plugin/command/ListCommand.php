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
        $header = ['插件名称', '版本', '状态', '描述'];
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

        $table = $this->table($header, $rows);
        $table->render();

        $output->writeln('');
        $output->writeln(sprintf('共 <info>%d</info> 个插件', count($plugins)));

        return 0;
    }
}
