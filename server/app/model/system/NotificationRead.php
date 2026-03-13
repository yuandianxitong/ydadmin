<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

class NotificationRead extends Model
{
    protected $table = 'notification_reads';

    protected $fillable = ['notification_id', 'admin_id', 'read_at'];

    protected $type = [
        'notification_id' => 'integer',
        'admin_id'        => 'integer',
    ];

    // 该表不需要 updated_at 和 deleted_at
    protected $updateTime = false;
    protected $deleteTime = false;

    public function notification(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'id');
    }

    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }
}
