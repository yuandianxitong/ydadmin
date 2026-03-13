<?php
declare(strict_types=1);

namespace app\model\message;

use core\base\Model;

class MessageTemplate extends Model
{
    protected $table = 'message_templates';

    protected $fillable = [
        'name', 'code',
        'sms_enabled', 'sms_template_id', 'sms_content',
        'wechat_official_enabled', 'wechat_official_template_id', 'wechat_official_url',
        'wechat_mini_enabled', 'wechat_mini_template_id', 'wechat_mini_page',
        'variables', 'remark', 'status',
    ];

    protected $type = [
        'sms_enabled'              => 'integer',
        'wechat_official_enabled'  => 'integer',
        'wechat_mini_enabled'      => 'integer',
        'status'                   => 'integer',
    ];

    protected $json = ['variables'];
    protected $jsonAssoc = true;
}
