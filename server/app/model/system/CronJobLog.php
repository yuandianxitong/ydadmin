<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

/**
 * 定时任务执行日志（cron_job_logs 表）。执行结束才写一行，写入后不再修改：
 * 显式关闭 UPDATED_AT，禁止引入 SoftDeletes（清空日志是硬删）。created_at 由 Eloquent 时间戳机制写入。
 */
class CronJobLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cron_job_logs';

    /** @var array<string, string> */
    protected $casts = [
        'cron_job_id' => 'integer',
        'trigger'     => 'integer',
        'status'      => 'integer',
        'duration'    => 'integer',
    ];
}
