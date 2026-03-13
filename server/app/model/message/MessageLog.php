<?php
declare(strict_types=1);

namespace app\model\message;

use think\Model as BaseModel;

class MessageLog extends BaseModel
{
    protected $table = 'message_logs';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $dateFormat = 'Y-m-d H:i:s';

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
