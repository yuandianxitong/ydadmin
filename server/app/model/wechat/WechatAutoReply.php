<?php

declare(strict_types=1);

namespace app\model\wechat;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 微信公众号自动回复（wechat_auto_replies 表，M6c spec §4.1）。软删；全局运营数据，无 created_by。
 *
 * Service 禁止引用本类常量（check:context 规则三），经 WechatAutoReplyRepository 转发。
 */
class WechatAutoReply extends Model
{
    use SoftDeletes;

    public const TYPE_KEYWORD = 'keyword';

    public const TYPE_SUBSCRIBE = 'subscribe';

    public const TYPE_DEFAULT = 'default';

    public const MATCH_EXACT = 'exact';

    public const MATCH_FUZZY = 'fuzzy';

    public const REPLY_TEXT = 'text';

    public const STATUS_ENABLED = 1;

    public const STATUS_DISABLED = 0;

    protected $table = 'wechat_auto_replies';

    /** @var array<string, string> */
    protected $casts = [
        'status'     => 'integer',
        'sort_order' => 'integer',
    ];
}
