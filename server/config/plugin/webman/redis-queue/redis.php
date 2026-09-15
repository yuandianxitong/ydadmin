<?php

// webman/redis-queue 连接配置（M3）。与 config/redis.php 共用 REDIS_* 环境变量，测试进程随 REDIS_DB=15。
//
// - host / auth / db 在两个客户端里含义一致：http 进程投递用的同步客户端（Webman\RedisQueue\Redis，
//   连接池 + Context 归还）与 queue 进程消费用的异步客户端（Workerman\RedisQueue\Client）。
//   auth 为 null 时两边都跳过 AUTH（异步客户端判 isset，同步客户端判 empty）。
// - retry_seconds 是全局的：重试延迟 = retry_seconds × 已尝试次数，包裹里改不了。
//   各队列只能配 max_attempts（config/queue.php），由 app\queue\ConsumerBase::onConsumeFailure() 写回包裹；
//   这里的 max_attempts 只是没有登记的队列的兜底值。
$password = (string) env('REDIS_PASSWORD', '');

return [
    'default' => [
        'host'    => 'redis://' . env('REDIS_HOST', '127.0.0.1') . ':' . (int) env('REDIS_PORT', 6379),
        'options' => [
            'auth'          => $password !== '' ? $password : null,
            'db'            => (int) env('REDIS_DB', 0),
            'prefix'        => '',
            'max_attempts'  => 5,
            'retry_seconds' => 10,
        ],
        // 同步客户端的连接池（Workerman\Coroutine\Pool）。默认 select 事件循环下同样可用：
        // 写计划时已实测每请求借出、随 Context::destroy() 归还，始终复用同一条连接。
        'pool' => [
            'max_connections'    => 5,
            'min_connections'    => 1,
            'wait_timeout'       => 3,
            'idle_timeout'       => 60,
            'heartbeat_interval' => 50,
        ],
    ],
];
