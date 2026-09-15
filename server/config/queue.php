<?php

/**
 * 队列（M3，spec §5）。
 *
 * queues：队列名 => 消费者类与最大重试次数。消费者须实现 core\queue\QueueHandler（继承 app\queue\ConsumerBase 即可），
 * 类放 app/queue/redis/ 供 queue 进程扫描。重试间隔是全局的（config/plugin/webman/redis-queue/redis.php 的
 * retry_seconds），这里只配 max_attempts：超过它仍失败就写入 failed_jobs 表。
 */
return [
    // redis：经 webman/redis-queue 投递到 Redis，由 queue 进程消费；sync：投递即在当前进程调用消费者的 handle()（测试专用）
    'driver' => env('QUEUE_DRIVER', 'redis'),

    'queues' => [
        'operation-log' => ['consumer' => app\queue\redis\OperationLogConsumer::class, 'max_attempts' => 3],
    ],
];
