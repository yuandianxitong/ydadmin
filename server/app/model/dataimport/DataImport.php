<?php

declare(strict_types=1);

namespace app\model\dataimport;

use core\base\Model;

/**
 * 数据导入记录（data_imports 表）。
 *
 * 无 deleted_at，不使用软删除。errors 是 JSON 文本，用 array cast。
 * Service/Controller 禁止引用本类常量（check:context 规则三），经 DataImportRepository 取值。
 */
class DataImport extends Model
{
    protected $table = 'data_imports';

    /** @var array<string, string> */
    protected $casts = [
        'id'            => 'integer',
        'total_count'   => 'integer',
        'success_count' => 'integer',
        'fail_count'    => 'integer',
        'status'        => 'integer',
        'admin_id'      => 'integer',
        'errors'        => 'array',
    ];

    /** @var list<string> */
    protected $appends = ['status_text'];

    public function getStatusTextAttribute(): string
    {
        if (!array_key_exists('status', $this->attributes)) {
            return '';
        }

        return match ((int) $this->attributes['status']) {
            0       => '处理中',
            1       => '完成',
            2       => '失败',
            default => '',
        };
    }
}
