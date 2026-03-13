<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use think\facade\Event;

class PluginLoader
{
    /**
     * 加载插件
     */
    public static function load(): void
    {
        // 应用初始化时加载插件
        Event::listen('app_init', function() {
            PluginManager::init();
        });
    }

    /**
     * 注册插件命令
     */
    public static function registerCommands(): void
    {
        // 注册插件相关的命令行工具
        $commands = [
            'plugin:list' => \core\plugin\command\ListCommand::class,
            'plugin:install' => \core\plugin\command\InstallCommand::class,
            'plugin:uninstall' => \core\plugin\command\UninstallCommand::class,
            'plugin:enable' => \core\plugin\command\EnableCommand::class,
            'plugin:disable' => \core\plugin\command\DisableCommand::class,
        ];

        foreach ($commands as $name => $class) {
            if (class_exists($class)) {
                \think\Console::addDefaultCommands([$name => $class]);
            }
        }
    }
}
