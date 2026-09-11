<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

/**
 * 通知已读记录（notification_reads 表）。唯一键 (notification_id, admin_id)，已读按管理员隔离。
 * 表里只有 created_at：关闭 UPDATED_AT。写入走 NotificationRepository 的 upsert，本模型只供 reads() 关联计数。
 */
class NotificationRead extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notification_reads';

    /** @var array<string, string> */
    protected $casts = [
        'notification_id' => 'integer',
        'admin_id'        => 'integer',
    ];
}
