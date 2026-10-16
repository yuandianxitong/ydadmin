<?php

use app\process\QueueConsumer;

// 日志、外部消息、定时命令与文件任务分别配置容量，队列名保持兼容。
return [
    'consumer' => [
        'handler' => QueueConsumer::class,
        'count' => max(1, (int) env('QUEUE_PROCESS_COUNT', 2)),
        'constructor' => ['consumer_dir' => app_path('queue/redis'), 'group' => 'logs'],
    ],
    'consumer_slow' => [
        'handler' => QueueConsumer::class,
        'count' => max(1, (int) env('QUEUE_SLOW_PROCESS_COUNT', 1)),
        'constructor' => ['consumer_dir' => app_path('queue/redis_cron'), 'group' => 'cron'],
    ],
    'consumer_messages' => [
        'handler' => QueueConsumer::class,
        'count' => max(1, (int) env('QUEUE_MESSAGE_PROCESS_COUNT', 2)),
        'constructor' => ['consumer_dir' => app_path('queue/redis_messages'), 'group' => 'messages'],
    ],
    'consumer_tasks' => [
        'handler' => QueueConsumer::class,
        'count' => max(1, (int) env('QUEUE_TASK_PROCESS_COUNT', 1)),
        'constructor' => ['consumer_dir' => app_path('queue/redis_tasks'), 'group' => 'tasks'],
    ],
];
