<?php
declare(strict_types=1);

namespace app\model\user;

use core\base\Model;

class PointsLog extends Model
{
    protected $updateTime = false;
    protected $deleteTime = false;
    protected $table = 'points_logs';
    protected $fillable = [
        'user_id', 'points', 'before_points', 'after_points',
        'type', 'source', 'remark', 'operator_id',
    ];
    protected $type = [
        'user_id'       => 'integer',
        'points'        => 'integer',
        'before_points' => 'integer',
        'after_points'  => 'integer',
        'type'          => 'integer',
        'operator_id'   => 'integer',
    ];

    const TYPE_ADMIN_ADJUST  = 1;
    const TYPE_REGISTER      = 2;
    const TYPE_SIGN_IN       = 3;
    const TYPE_CONSUME_AWARD = 4;
    const TYPE_CONSUME_DEDUCT = 5;
    const TYPE_MAP = [
        self::TYPE_ADMIN_ADJUST   => '后台调整',
        self::TYPE_REGISTER       => '注册赠送',
        self::TYPE_SIGN_IN        => '签到',
        self::TYPE_CONSUME_AWARD  => '消费赠送',
        self::TYPE_CONSUME_DEDUCT => '消费扣减',
    ];

    public function getTypeTextAttr($value, $data): string
    {
        return self::TYPE_MAP[$data['type']] ?? '未知';
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
