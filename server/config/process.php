<?php

use app\process\Http;
use support\Log;
use support\Request;

global $argv;

return [
    'webman' => [
        'handler'     => Http::class,
        'listen'      => env('SERVER_LISTEN', 'http://0.0.0.0:8000'),
        'count'       => cpu_count(),
        'user'        => '',
        'group'       => '',
        'reusePort'   => false,
        'eventLoop'   => '',
        'context'     => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger'       => Log::channel('default'),
            'appPath'      => app_path(),
            'publicPath'   => public_path(),
        ],
    ],
    // 开发期文件变更自动重载；-d 守护模式（生产）下关闭文件监控
    'monitor' => [
        'handler'     => app\process\Monitor::class,
        'reloadable'  => false,
        'constructor' => [
            'monitorDir' => [
                app_path(),
                base_path() . '/core',
                config_path(),
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ],
            'monitorExtensions' => ['php', 'env'],
            'options' => [
                'enable_file_monitor'   => !in_array('-d', $argv, true) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ],
        ],
    ],
    // 定时任务调度（M3）：每分钟判定到点任务并投递到 cron-job 队列。必须 count=1——多进程会各判定一遍，
    // 触发锁虽能去重，但每个进程每分钟都白查一次库。队列消费进程由 webman/redis-queue 插件自己注册
    // （config/plugin/webman/redis-queue/process.php）。
    'scheduler' => [
        'handler' => app\process\Scheduler::class,
        'count'   => 1,
    ],
];
