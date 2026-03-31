<?php
declare(strict_types=1);

namespace app\model\message;

use core\base\Model;

class MessageLog extends Model
{
    protected $table = 'message_logs';

    // 日志表不需要自动更新时间和软删除
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $fillable = [
        'template_id', 'template_code', 'channel', 'receiver',
        'content', 'variables', 'status', 'error_msg', 'sent_at',
    ];

    protected $type = [
        'template_id' => 'integer',
        'status'      => 'integer',
    ];

    protected $json = ['variables'];
    protected $jsonAssoc = true;

    public function template(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id', 'id');
    }
}
