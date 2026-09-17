<?php

declare(strict_types=1);

namespace app\model\message;

use core\base\Model;

/**
 * 会员站内信已读记录（user_notification_reads 表，M6b spec §2.4）。表里只有 read_at，没有 created_at/updated_at：
 * 关闭时间戳。写入走 UserNotificationReadRepository 的 INSERT IGNORE。
 */
class UserNotificationRead extends Model
{
    public $timestamps = false;

    protected $table = 'user_notification_reads';

    /** @var array<string, string> */
    protected $casts = [
        'notification_id' => 'integer',
        'user_id'         => 'integer',
    ];
}
