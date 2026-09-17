<?php

// webman/redis-queue 消费进程（M3）。启动时扫描 consumer_dir 下每个实现了 Webman\RedisQueue\Consumer 的类，
// 按类上的公开属性 $queue 订阅队列。
//
// 这些目录只能放可实例化的消费者：消费进程会对扫到的每个类 Container::get()，抽象类会让进程启动即崩——
// 所以抽象基类 app\queue\ConsumerBase 放在 app/queue/，不放进子目录。
//
// 按耗时分两组（M6b）：consumer 处理写操作日志这类毫秒级任务；consumer_slow 处理执行定时任务命令、调用短信与微信接口
// 这类单次可能耗时数秒的任务，免得慢任务占满进程、操作日志堆在 Redis 里迟迟不落库。
return [
    'consumer' => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => max(1, (int) env('QUEUE_PROCESS_COUNT', 2)),
        'constructor' => [
            'consumer_dir' => app_path() . '/queue/redis',
        ],
    ],
    'consumer_slow' => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => max(1, (int) env('QUEUE_SLOW_PROCESS_COUNT', 1)),
        'constructor' => [
            'consumer_dir' => app_path() . '/queue/redis_slow',
        ],
    ],
];
