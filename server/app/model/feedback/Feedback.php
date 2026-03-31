<?php
declare(strict_types=1);

namespace app\model\feedback;

use core\base\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id', 'type', 'content', 'images', 'contact',
        'status', 'reply', 'replied_at', 'replied_by',
    ];

    // 状态常量
    const STATUS_PENDING    = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_REPLIED    = 2;
    const STATUS_CLOSED     = 3;

    protected $type = [
        'user_id'    => 'integer',
        'status'     => 'integer',
        'replied_by' => 'integer',
    ];

    protected $append = ['status_text'];

    /**
     * 图片获取器
     */
    public function getImagesAttr($value): array
    {
        return $this->getJsonAttr($value);
    }

    /**
     * 图片修改器
     */
    public function setImagesAttr($value): string
    {
        return $this->setJsonAttr($value);
    }

    /**
     * 状态文本获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_PENDING    => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_REPLIED    => '已回复',
            self::STATUS_CLOSED     => '已关闭',
        ];
        return $this->getStatusText((int) ($data['status'] ?? 0), $statusMap);
    }
}
