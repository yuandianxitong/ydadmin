<?php

declare(strict_types=1);

namespace app\model\version;

use core\base\Model;

/** 应用版本（app_versions 表）。由代码生成器生成，可直接手改。 */
class AppVersion extends Model
{
    protected $table = 'app_versions';

    /** @var array<string, string> */
    protected $casts = [
        'id'           => 'int',
        'version_code' => 'int',
        'force_update' => 'int',
        'status'       => 'int',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /** @var list<string> */
    protected $appends = ['status_text'];

    /** status 的可读文案：1 正常，其余禁用（与 Dictionary 等手写模块一致）。 */
    public function getStatusTextAttribute(): string
    {
        if (!array_key_exists('status', $this->attributes)) {
            return '';
        }

        return (int) $this->attributes['status'] === 1 ? '正常' : '禁用';
    }
}
