<?php
declare(strict_types=1);

namespace app\model\agreement;

use core\base\Model;

class Agreement extends Model
{
    protected $table = 'agreements';

    protected $deleteTime = false;
    protected $hidden = [];

    protected $fillable = [
        'title', 'code', 'content', 'status',
    ];

    protected $type = [
        'status' => 'integer',
    ];

    protected $append = ['status_text'];

    /**
     * 状态文本获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            0 => '禁用',
            1 => '启用',
        ];
        return $this->getStatusText((int) ($data['status'] ?? 0), $statusMap);
    }
}
