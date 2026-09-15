<?php

// webman/redis-queue 消费进程（M3）。启动时扫描 app/queue/redis/ 下每个实现了 Webman\RedisQueue\Consumer 的类，
// 按类上的公开属性 $queue 订阅队列。
//
// 这个目录只能放可实例化的消费者：消费进程会对扫到的每个类 Container::get()，抽象类会让进程启动即崩——
// 所以抽象基类 app\queue\ConsumerBase 放在 app/queue/，不放进 redis/ 子目录。
return [
    'consumer' => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => max(1, (int) env('QUEUE_PROCESS_COUNT', 2)),
        'constructor' => [
            'consumer_dir' => app_path() . '/queue/redis',
        ],
    ],
];
