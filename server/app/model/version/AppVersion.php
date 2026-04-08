<?php
declare(strict_types=1);

namespace app\model\version;

use core\base\Model;

class AppVersion extends Model
{
    protected $name = 'app_versions';

    protected $deleteTime = false;
    protected $hidden = [];

    protected $fillable = [
        'platform', 'version', 'version_code', 'download_url',
        'description', 'force_update', 'status',
    ];

    protected $type = [
        'version_code' => 'integer',
        'force_update' => 'integer',
        'status'       => 'integer',
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
