<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 站内通知（notifications 表，契约 §2.10）。有 deleted_at → SoftDeletes；不受数据权限约束。 */
class Notification extends Model
{
    use SoftDeletes;

    private const TYPE_TEXT = [1 => '系统通知', 2 => '待办提醒', 3 => '业务消息'];

    private const TARGET_TYPE_TEXT = [1 => '全部用户', 2 => '指定用户'];

    protected $table = 'notifications';

    /** @var array<string, string> */
    protected $casts = [
        'type'        => 'integer',
        'sender_id'   => 'integer',
        'target_type' => 'integer',
        'status'      => 'integer',
    ];

    /** @var list<string> */
    protected $appends = ['type_text', 'target_type_text'];

    public function getTypeTextAttribute(): string
    {
        return self::TYPE_TEXT[(int) ($this->attributes['type'] ?? 0)] ?? '';
    }

    public function getTargetTypeTextAttribute(): string
    {
        return self::TARGET_TYPE_TEXT[(int) ($this->attributes['target_type'] ?? 0)] ?? '';
    }

    /**
     * 已读记录（每个管理员一行）。
     *
     * @return HasMany<NotificationRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class, 'notification_id');
    }
}
