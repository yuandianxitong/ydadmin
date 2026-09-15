<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

/**
 * 管理员操作日志（admin_operation_logs 表）。AdminLogMiddleware 在请求内算好载荷投递到 operation-log 队列，
 * 由 OperationLogConsumer 落库；投递失败时中间件同步回退写库（M3，spec §8.4）。
 *
 * 无 updated_at、无 deleted_at（日志表不软删）：显式关闭 UPDATED_AT，禁止引入 SoftDeletes。
 * params/result 是 JSON 列，cast 为 array；operation_time 是请求时刻，由 Repository::record() 从载荷取值
 * （与队列落库时刻不同），created_at 由 Eloquent 时间戳机制写入。
 */
class AdminOperationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_operation_logs';

    /** @var array<string, string> */
    protected $casts = [
        'admin_id'       => 'integer',
        'params'         => 'array',
        'result'         => 'array',
        'operation_time' => 'datetime',
        'execution_time' => 'float',
    ];
}
