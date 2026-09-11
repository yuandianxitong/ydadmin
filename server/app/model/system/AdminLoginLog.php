<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

/**
 * 管理员登录日志（admin_login_logs 表）。
 *
 * 无 updated_at、无 deleted_at（日志表不软删）——显式关闭 Eloquent 默认维护的
 * UPDATED_AT，禁止引入 SoftDeletes trait。created_at 由 Eloquent 时间戳机制自动
 * 写入（CREATED_AT 用默认值 'created_at'），login_time 由 Repository::record()
 * 显式赋值（业务语义的登录时间，与建档时间分离）。
 *
 * login_result 显式 cast 为 integer（不是 boolean）——前端契约期望列表响应里的
 * login_result 是 number(1/0)，cast 成 boolean 会在 JSON 序列化时输出
 * true/false，与前端渲染逻辑不符。DB 列本身是 `int(4)`，cast=integer 与列
 * 类型语义一致，getLoginResultTextAttribute() 的真值判断对 1/0 同样成立。
 */
class AdminLoginLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_login_logs';

    /** @var array<string, string> */
    protected $casts = [
        'admin_id'     => 'integer',
        'login_result' => 'integer',
        'login_time'   => 'datetime',
    ];

    /** @var array<int, string> */
    protected $appends = ['login_result_text'];

    public function getLoginResultTextAttribute(): string
    {
        if (!array_key_exists('login_result', $this->attributes)) {
            return '';
        }

        return $this->attributes['login_result'] ? '成功' : '失败';
    }
}
