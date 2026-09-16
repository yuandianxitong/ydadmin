<?php

declare(strict_types=1);

namespace app\model\user;

use core\base\Model;

/**
 * 余额变动流水（balance_logs 表，spec §2.2）。无软删、无 updated_at（表里没有该列）：
 * 显式关闭 UPDATED_AT，禁止引入 SoftDeletes。
 * type_text 由访问器生成，文案走 lang()，中文逐字对齐 TP8，英文补齐（spec §5.3）——
 * 不硬编码中文：管理端有英文语言包，硬编码会让英文界面看到中文文案。
 */
class BalanceLog extends Model
{
    public const TYPE_RECHARGE = 1;

    public const TYPE_CONSUME = 2;

    public const TYPE_REFUND = 3;

    public const TYPE_ADMIN_ADJUST = 4;

    public const UPDATED_AT = null;

    protected $table = 'balance_logs';

    /** @var array<string, string> */
    protected $casts = [
        'user_id'        => 'integer',
        'amount'         => 'decimal:2',
        'before_balance' => 'decimal:2',
        'after_balance'  => 'decimal:2',
        'type'           => 'integer',
        'operator_id'    => 'integer',
    ];

    /** @var list<string> */
    protected $appends = ['type_text'];

    public function getTypeTextAttribute(): string
    {
        if (!array_key_exists('type', $this->attributes)) {
            return '';
        }

        return match ((int) $this->attributes['type']) {
            self::TYPE_RECHARGE      => lang('business.balance_log_type_1'),
            self::TYPE_CONSUME       => lang('business.balance_log_type_2'),
            self::TYPE_REFUND        => lang('business.balance_log_type_3'),
            self::TYPE_ADMIN_ADJUST  => lang('business.balance_log_type_4'),
            default                  => '',
        };
    }
}
