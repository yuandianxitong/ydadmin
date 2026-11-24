<?php

declare(strict_types=1);

namespace core\plugin;

use support\Log;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Webman\Console\Command;

/**
 * 把已装载插件在 manifest.console 里声明的命令类注册进 webman 命令行。
 *
 * 取数用 PluginManager::loadedVersions() 而不是 PluginRegistry::all()：registry 在扫描阶段就写入，
 * 未启用的插件也在里面。saga 子进程（宿主关掉了插件预加载）不装载插件，这里自然是空循环。
 */
class PluginConsoleRegistrar
{
    /**
     * @return string[] 本次实际注册成功的命令类名列表（按注册顺序），供调用方
     *                   日志/测试断言使用
     */
    public function register(Command $cli, PluginManager $pluginManager, PluginRegistry $pluginRegistry): array
    {
        $registered = [];

        foreach (array_keys($pluginManager->loadedVersions()) as $pluginCode) {
            $meta = $pluginRegistry->get($pluginCode);
            $commandClasses = (array) ($meta['manifest']['console'] ?? []);

            foreach ($commandClasses as $commandClass) {
                if (!is_string($commandClass) || $commandClass === '') {
                    continue;
                }

                // 整段包在 catch (\Throwable) 里：class_exists() 会触发自动加载，语法错误的
                // 命令类文件会抛 \ParseError。一个坏类不能拖垮其它命令和整个 CLI。
                try {
                    // 镜像 Command::installCommands() 的跳过逻辑，createCommandInstance() 自己不做这层检查。
                    if (!class_exists($commandClass) || !is_a($commandClass, ConsoleCommand::class, true)) {
                        Log::warning(
                            "[plugin-console] 插件 {$pluginCode} 在 manifest.console 里声明的命令类 "
                            . "{$commandClass} 不存在或不是 Symfony Command 子类，已跳过。"
                        );
                        continue;
                    }

                    // 抽象类会静默返回 null，不能记为已注册；缺 defaultName/#[AsCommand] 则抛异常。
                    $command = $cli->createCommandInstance($commandClass);
                    if ($command === null) {
                        Log::warning(
                            "[plugin-console] 插件 {$pluginCode} 命令类 {$commandClass} 是抽象类，"
                            . 'createCommandInstance() 未产出可用命令实例，已跳过。'
                        );
                        continue;
                    }
                    $registered[] = $commandClass;
                } catch (\Throwable $e) {
                    Log::error(
                        "[plugin-console] 插件 {$pluginCode} 命令类 {$commandClass} 注册失败，已跳过："
                        . $e->getMessage()
                    );
                }
            }
        }

        return $registered;
    }
}
