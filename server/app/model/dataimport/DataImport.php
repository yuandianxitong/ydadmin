<?php
declare(strict_types=1);

namespace app\model\dataimport;

use core\base\Model;

class DataImport extends Model
{
    protected $table = 'data_imports';

    protected $deleteTime = false;
    protected $hidden = [];

    protected $fillable = [
        'module', 'filename', 'total_count', 'success_count',
        'fail_count', 'status', 'errors', 'admin_id',
    ];

    // 状态常量
    const STATUS_PROCESSING = 0;
    const STATUS_COMPLETED  = 1;
    const STATUS_FAILED     = 2;

    protected $type = [
        'total_count'   => 'integer',
        'success_count' => 'integer',
        'fail_count'    => 'integer',
        'status'        => 'integer',
        'admin_id'      => 'integer',
    ];

    /**
     * 错误信息获取器
     */
    public function getErrorsAttr($value): array
    {
        return $this->getJsonAttr($value);
    }

    /**
     * 错误信息修改器
     */
    public function setErrorsAttr($value): string
    {
        return $this->setJsonAttr($value);
    }

    /**
     * 状态文本获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED  => '完成',
            self::STATUS_FAILED     => '失败',
        ];
        return $this->getStatusText((int) ($data['status'] ?? 0), $statusMap);
    }
}
