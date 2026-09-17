<?php

declare(strict_types=1);

namespace app\model\message;

use core\base\Model;

/**
 * 消息发送日志（message_logs 表，M6b spec §2.2）。每个通道一行；无软删。
 * receiver 只存遮蔽后的展示值，发送时按 user_id 重读真实接收人（spec §4.4）。
 *
 * Service 禁止引用本类常量（check:context 规则三），经 MessageLogRepository 的转发常量取值。
 */
class MessageLog extends Model
{
    public const STATUS_PENDING = 0;

    public const STATUS_SUCCESS = 1;

    public const STATUS_FAILED = 2;

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WECHAT_OFFICIAL = 'wechat_official';

    public const CHANNEL_WECHAT_MINI = 'wechat_mini';

    public const CHANNEL_SITE = 'site';

    protected $table = 'message_logs';

    /** @var array<string, string> */
    protected $casts = [
        'template_id' => 'integer',
        'user_id'     => 'integer',
        'status'      => 'integer',
        'attempts'    => 'integer',
        'variables'   => 'array',
    ];
}
