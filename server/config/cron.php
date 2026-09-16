<?php

/**
 * 定时任务（M3 spec §5）。
 *
 * commands：白名单，命令名 => 命令类。定时任务的 command 字段首词必须是这里的键，参数原样传给命令。
 *   键必须与命令类 #[AsCommand] 的名字一致。只放「定时跑也安全」的命令——db:reset、admin:init、
 *   make:crud 这类破坏性或交互式命令永远不能进来。新增一条要同时写测试。
 */
return [
    'commands' => [
        'log:archive'           => app\command\LogArchiveCommand::class,
        // M5b：关单。单轮至多 payment.close_batch 单，网关调用都带超时；payment:refund 永远不进这里（设计决定 14）
        'payment:close-expired' => app\command\PaymentCloseExpiredCommand::class,
    ],
    // 执行锁 cron:running:{id} 的 TTL（秒）：命令跑得比它久，锁会提前过期，下一周期可能与之重叠
    'lock_ttl'            => 3600,
    // 手动执行时 http 进程 BLPOP 等结果的上限（秒，≥1）；Redis 连接的读超时必须大于它
    'manual_wait_seconds' => 10,
    // 调度器补算的最大分钟数：缺口不超过它就逐分钟补，超过只算当前分钟
    'catchup_minutes'     => 5,
];
