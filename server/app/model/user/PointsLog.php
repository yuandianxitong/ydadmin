<?php

declare(strict_types=1);

namespace app\model\user;

use core\base\Model;

/**
 * 积分变动流水（points_logs 表，spec §2.3）。无软删、无 updated_at（表里没有该列）：
 * 显式关闭 UPDATED_AT，禁止引入 SoftDeletes。type_text 同 BalanceLog：访问器 + lang()，不硬编码中文。
 */
class PointsLog extends Model
{
    public const TYPE_ADMIN_ADJUST = 1;

    public const TYPE_REGISTER = 2;

    public const TYPE_SIGN_IN = 3;

    public const TYPE_CONSUME_AWARD = 4;

    public const TYPE_CONSUME_DEDUCT = 5;

    public const UPDATED_AT = null;

    protected $table = 'points_logs';

    /** @var array<string, string> */
    protected $casts = [
        'user_id'       => 'integer',
        'points'        => 'integer',
        'before_points' => 'integer',
        'after_points'  => 'integer',
        'type'          => 'integer',
        'operator_id'   => 'integer',
    ];

    /** @var list<string> */
    protected $appends = ['type_text'];

    public function getTypeTextAttribute(): string
    {
        if (!array_key_exists('type', $this->attributes)) {
            return '';
        }

        return match ((int) $this->attributes['type']) {
            self::TYPE_ADMIN_ADJUST   => lang('business.points_log_type_1'),
            self::TYPE_REGISTER       => lang('business.points_log_type_2'),
            self::TYPE_SIGN_IN        => lang('business.points_log_type_3'),
            self::TYPE_CONSUME_AWARD  => lang('business.points_log_type_4'),
            self::TYPE_CONSUME_DEDUCT => lang('business.points_log_type_5'),
            default                   => '',
        };
    }
}
