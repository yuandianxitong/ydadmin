<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        \app\command\MakeCrudCommand::class,
        \app\command\GenerateApiDocCommand::class,
        'log:archive' => \app\command\LogArchiveCommand::class,
        \app\command\YdAiLoginCommand::class,
    ],
];
