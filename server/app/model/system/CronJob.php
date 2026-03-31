<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

class CronJob extends Model
{
    protected $table = 'cron_jobs';

    protected $fillable = [
        'name', 'command', 'expression', 'description', 'status',
        'last_run_at', 'last_result', 'last_status', 'run_count',
        'sort', 'created_by'
    ];

    protected $type = [
        'status'      => 'integer',
        'last_status' => 'integer',
        'run_count'   => 'integer',
        'sort'        => 'integer',
    ];

    protected $append = ['status_text', 'last_status_text'];

    public function logs(): \think\model\relation\HasMany
    {
        return $this->hasMany(CronJobLog::class, 'cron_job_id', 'id');
    }

    public function getStatusTextAttr($value, $data): string
    {
        return $this->getStatusText((int) ($data['status'] ?? 0), [1 => '启用', 0 => '禁用']);
    }

    public function getLastStatusTextAttr($value, $data): string
    {
        $map = [1 => '成功', 0 => '失败'];
        return $map[$data['last_status'] ?? ''] ?? '-';
    }
}
