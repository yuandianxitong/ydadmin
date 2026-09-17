<?php

declare(strict_types=1);

namespace app\model\message;

use core\base\Model;

/** 会员站内信（user_notifications 表，M6b spec §2.3）。一行一个接收会员，不支持广播；无软删。 */
class UserNotification extends Model
{
    protected $table = 'user_notifications';

    /** @var array<string, string> */
    protected $casts = [
        'user_id' => 'integer',
        'extra'   => 'array',
    ];
}
