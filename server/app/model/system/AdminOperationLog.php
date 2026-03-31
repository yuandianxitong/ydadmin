<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use think\model\relation\BelongsTo;

class AdminOperationLog extends Model
{
    protected $table = 'admin_operation_logs';

    // 日志表不需要自动更新时间和软删除
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $fillable = [
        'admin_id', 'username', 'method', 'path', 'ip', 'user_agent',
        'action', 'description', 'params', 'result', 'operation_time',
        'execution_time'
    ];

    protected $type = [
        'admin_id' => 'integer',
        'params' => 'json',
        'result' => 'json',
        'operation_time' => 'datetime',
        'execution_time' => 'float',
    ];

    // 关联管理员
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 记录操作日志
     */
    public static function record(array $data): void
    {
        self::create([
            'admin_id' => $data['admin_id'] ?? 0,
            'username' => $data['username'] ?? '',
            'method' => $data['method'] ?? '',
            'path' => $data['path'] ?? '',
            'ip' => $data['ip'] ?? '',
            'user_agent' => $data['user_agent'] ?? '',
            'action' => $data['action'] ?? '',
            'description' => $data['description'] ?? '',
            'params' => $data['params'] ?? [],
            'result' => $data['result'] ?? [],
            'operation_time' => date('Y-m-d H:i:s'),
            'execution_time' => $data['execution_time'] ?? 0,
        ]);
    }
}
