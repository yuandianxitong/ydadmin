<?php

declare(strict_types=1);

namespace app\model\feedback;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 用户反馈（feedbacks 表）。
 * Service/Controller 禁止引用本类常量（check:context 规则三），经 FeedbackRepository 的转发常量取值。
 */
class Feedback extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 0;

    public const STATUS_PROCESSING = 1;

    public const STATUS_REPLIED = 2;

    public const STATUS_CLOSED = 3;

    protected $table = 'feedbacks';

    /** @var array<string, string> */
    protected $casts = [
        'id'         => 'int',
        'user_id'    => 'int',
        'status'     => 'int',
        'images'     => 'array',
        'replied_by' => 'int',
        'replied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @var list<string> */
    protected $appends = ['status_text'];

    public function getStatusTextAttribute(): string
    {
        if (!array_key_exists('status', $this->attributes)) {
            return '';
        }

        return match ((int) $this->attributes['status']) {
            self::STATUS_PENDING    => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_REPLIED    => '已回复',
            self::STATUS_CLOSED     => '已关闭',
            default                 => '',
        };
    }
}
