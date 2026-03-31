<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'title', 'content', 'type', 'sender_id', 'target_type', 'status'
    ];

    protected $type = [
        'type'        => 'integer',
        'sender_id'   => 'integer',
        'target_type' => 'integer',
        'status'      => 'integer',
    ];

    protected $append = ['type_text', 'target_type_text'];

    public function sender(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_id', 'id');
    }

    public function reads(): \think\model\relation\HasMany
    {
        return $this->hasMany(NotificationRead::class, 'notification_id', 'id');
    }

    public function getTypeTextAttr($value, $data): string
    {
        $map = [1 => '系统通知', 2 => '待办提醒', 3 => '业务消息'];
        return $map[(int) ($data['type'] ?? 1)] ?? '未知';
    }

    public function getTargetTypeTextAttr($value, $data): string
    {
        $map = [1 => '全部用户', 2 => '指定用户'];
        return $map[(int) ($data['target_type'] ?? 1)] ?? '未知';
    }
}
