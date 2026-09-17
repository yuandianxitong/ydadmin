<?php

/**
 * 队列（M3，spec §5）。
 *
 * queues：队列名 => 消费者类与最大重试次数。消费者须实现 core\queue\QueueHandler（继承 app\queue\ConsumerBase 即可），
 * 按耗时放进 app/queue/redis/（consumer 进程组）或 app/queue/redis_slow/（consumer_slow 进程组，M6b），
 * 见 config/plugin/webman/redis-queue/process.php。重试间隔是全局的（config/plugin/webman/redis-queue/redis.php 的
 * retry_seconds），这里只配 max_attempts：超过它仍失败就写入 failed_jobs 表。
 */
return [
    // redis：经 webman/redis-queue 投递到 Redis，由 queue 进程消费；sync：投递即在当前进程调用消费者的 handle()（测试专用）
    'driver' => env('QUEUE_DRIVER', 'redis'),

    'queues' => [
        'operation-log' => ['consumer' => app\queue\redis\OperationLogConsumer::class, 'max_attempts' => 3],
        'cron-job'      => ['consumer' => app\queue\redis_slow\CronJobConsumer::class, 'max_attempts' => 0],
        // 发送短信与微信消息（M6b spec §4.4）：确定失败不重试、暂时失败至多 3 次
        'message-send'  => ['consumer' => app\queue\redis_slow\MessageSendConsumer::class, 'max_attempts' => 3],
    ],
];
