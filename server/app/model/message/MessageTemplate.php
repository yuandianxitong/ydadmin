<?php

declare(strict_types=1);

namespace app\model\message;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 消息模板（message_templates 表，M6b spec §2.1）。软删；code 唯一且含软删行（uk_code 不区分软删）。
 * 四通道各自一个开关；微信两通道的字段映射 {字段名: 含 ${var} 的文本} 存 JSON。
 *
 * Service 禁止引用本类常量（check:context 规则三），经 MessageTemplateRepository 的转发常量取值。
 */
class MessageTemplate extends Model
{
    use SoftDeletes;

    public const STATUS_ENABLED = 1;

    protected $table = 'message_templates';

    /** @var array<string, string> */
    protected $casts = [
        'status'                  => 'integer',
        'sms_enabled'             => 'integer',
        'wechat_official_enabled' => 'integer',
        'wechat_mini_enabled'     => 'integer',
        'site_enabled'            => 'integer',
        'wechat_official_data'    => 'array',
        'wechat_mini_data'        => 'array',
        'variables'               => 'array',
    ];
}
