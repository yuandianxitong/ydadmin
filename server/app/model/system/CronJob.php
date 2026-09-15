<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 定时任务（cron_jobs 表，M3 spec §6）。有 deleted_at → SoftDeletes；系统表，不接数据权限。
 *
 * 列名 expression 沿用 TP8；接口层同时以 expression 与 cron_expression 两个键输出（CronJobService 负责）。
 */
class CronJob extends Model
{
    use SoftDeletes;

    protected $table = 'cron_jobs';

    /** @var array<string, string> */
    protected $casts = [
        'status'      => 'integer',
        'last_status' => 'integer',
        'run_count'   => 'integer',
        'sort'        => 'integer',
        'created_by'  => 'integer',
    ];
}
