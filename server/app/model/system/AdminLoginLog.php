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

class AdminLoginLog extends Model
{
    protected $table = 'admin_login_logs';

    // 日志表不需要自动更新时间和软删除
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $fillable = [
        'admin_id', 'username', 'ip', 'user_agent', 'login_time',
        'login_result', 'login_message', 'browser', 'os'
    ];

    protected $type = [
        'admin_id' => 'integer',
        'login_result' => 'boolean',
        'login_time' => 'datetime',
    ];

    protected $append = ['login_result_text'];

    // 关联管理员
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    // 访问器
    public function getLoginResultTextAttr($value, $data): string
    {
        if (!isset($data['login_result'])) {
            return '';
        }
        return $data['login_result'] ? '成功' : '失败';
    }
}
