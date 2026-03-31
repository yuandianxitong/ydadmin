<?php
declare(strict_types=1);

namespace app\model\user;

use core\base\Model;

class BalanceLog extends Model
{
    protected $updateTime = false;
    protected $deleteTime = false;
    protected $table = 'balance_logs';
    protected $fillable = [
        'user_id', 'amount', 'before_balance', 'after_balance',
        'type', 'source', 'remark', 'operator_id',
    ];
    protected $type = [
        'user_id'        => 'integer',
        'amount'         => 'float',
        'before_balance' => 'float',
        'after_balance'  => 'float',
        'type'           => 'integer',
        'operator_id'    => 'integer',
    ];
    protected $append = ['type_text'];

    const TYPE_RECHARGE     = 1;
    const TYPE_CONSUME      = 2;
    const TYPE_REFUND       = 3;
    const TYPE_ADMIN_ADJUST = 4;
    const TYPE_MAP = [
        self::TYPE_RECHARGE     => '充值',
        self::TYPE_CONSUME      => '消费',
        self::TYPE_REFUND       => '退款',
        self::TYPE_ADMIN_ADJUST => '后台调整',
    ];

    public function getTypeTextAttr($value, $data): string
    {
        if (!isset($data['type'])) return '';
        return self::TYPE_MAP[$data['type']] ?? '未知';
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
