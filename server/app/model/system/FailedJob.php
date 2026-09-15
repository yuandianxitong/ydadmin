<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

/**
 * 队列失败任务（failed_jobs 表，M3）。只写入与删除，不更新：没有 created_at / updated_at，时间用 failed_at。
 * payload 是 JSON 列（写入前已脱敏），cast 为 array。
 */
class FailedJob extends Model
{
    public $timestamps = false;

    protected $table = 'failed_jobs';

    /** @var array<string, string> */
    protected $casts = [
        'payload'  => 'array',
        'attempts' => 'integer',
    ];
}
