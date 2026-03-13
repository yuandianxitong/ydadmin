<?php
declare(strict_types=1);

namespace app\model\wechat;

use core\base\Model;

class WechatAutoReply extends Model
{
    protected $table = 'wechat_auto_replies';

    protected $fillable = [
        'type', 'keyword', 'match_type', 'reply_type',
        'content', 'status', 'sort_order',
    ];

    protected $type = [
        'status'     => 'integer',
        'sort_order' => 'integer',
    ];
}
