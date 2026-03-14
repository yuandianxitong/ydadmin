<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        \app\command\MakeCrudCommand::class,
        \app\command\GenerateApiDocCommand::class,
        \core\plugin\command\ListCommand::class,
        \core\plugin\command\InstallCommand::class,
        \core\plugin\command\UninstallCommand::class,
        \core\plugin\command\EnableCommand::class,
        \core\plugin\command\DisableCommand::class,
    ],
];
