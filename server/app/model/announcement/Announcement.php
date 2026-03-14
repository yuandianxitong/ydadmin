<?php
declare(strict_types=1);

namespace app\model\announcement;

use core\base\Model;

class Announcement extends Model
{
    protected $table = 'announcements';

    protected $fillable = [
        'title', 'content', 'type', 'status', 'sort',
        'publish_at', 'admin_id',
    ];

    // 类型常量
    const TYPE_NOTICE   = 1;
    const TYPE_UPDATE   = 2;
    const TYPE_ACTIVITY = 3;

    // 状态常量
    const STATUS_DRAFT     = 0;
    const STATUS_PUBLISHED = 1;

    protected $type = [
        'type'     => 'integer',
        'status'   => 'integer',
        'sort'     => 'integer',
        'admin_id' => 'integer',
    ];

    /**
     * 类型文本获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $typeMap = [
            self::TYPE_NOTICE   => '通知',
            self::TYPE_UPDATE   => '更新',
            self::TYPE_ACTIVITY => '活动',
        ];
        return $typeMap[(int) ($data['type'] ?? 0)] ?? '未知';
    }

    /**
     * 状态文本获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_DRAFT     => '草稿',
            self::STATUS_PUBLISHED => '已发布',
        ];
        return $this->getStatusText((int) ($data['status'] ?? 0), $statusMap);
    }
}
