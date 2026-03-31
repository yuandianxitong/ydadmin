<?php
declare(strict_types=1);

namespace app\model\notification;

use core\base\Model;

class UserNotification extends Model
{
    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id', 'title', 'content', 'type', 'biz_id', 'extra',
    ];

    protected $type = [
        'user_id' => 'integer',
        'biz_id'  => 'integer',
    ];

    protected $append = ['type_text'];

    // 类型常量
    const TYPE_SYSTEM   = 'system';
    const TYPE_ORDER    = 'order';
    const TYPE_PAYMENT  = 'payment';
    const TYPE_FEEDBACK = 'feedback';

    /**
     * extra 字段获取器
     */
    public function getExtraAttr($value): array
    {
        return $this->getJsonAttr($value);
    }

    /**
     * extra 字段修改器
     */
    public function setExtraAttr($value): string
    {
        return $this->setJsonAttr($value);
    }

    /**
     * 类型文本获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $map = [
            self::TYPE_SYSTEM   => '系统通知',
            self::TYPE_ORDER    => '订单消息',
            self::TYPE_PAYMENT  => '支付消息',
            self::TYPE_FEEDBACK => '反馈消息',
        ];
        return $map[$data['type'] ?? self::TYPE_SYSTEM] ?? '未知';
    }
}
