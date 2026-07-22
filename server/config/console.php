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
        'yd:update' => \app\command\YdUpdateCommand::class,
        \app\command\YdAiLoginCommand::class,
        'yd:ai:doctor' => \app\command\YdAiDoctorCommand::class,
        'yd:ai' => \app\command\YdAiCommand::class,
    ],
];
