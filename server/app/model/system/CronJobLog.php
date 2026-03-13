<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

class CronJobLog extends Model
{
    protected $table = 'cron_job_logs';

    // 日志表无软删除
    protected $deleteTime = false;

    protected $fillable = [
        'cron_job_id', 'status', 'output', 'error',
        'started_at', 'finished_at', 'duration'
    ];

    protected $type = [
        'cron_job_id' => 'integer',
        'status'      => 'integer',
        'duration'    => 'integer',
    ];

    protected $updateTime = false;

    public function cronJob(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(CronJob::class, 'cron_job_id', 'id');
    }

    public function getStatusTextAttr($value, $data): string
    {
        $map = [1 => '成功', 0 => '失败'];
        return $map[(int) ($data['status'] ?? 0)] ?? '未知';
    }
}
