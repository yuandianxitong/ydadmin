<?php

declare(strict_types=1);

namespace app\model\region;

use core\base\Model;

/** 地区（regions 表）。由代码生成器生成，可直接手改。 */
class Region extends Model
{
    protected $table = 'regions';

    /** @var array<string, string> */
    protected $casts = [
        'id'        => 'int',
        'parent_id' => 'int',
        'level'     => 'int',
        'sort'      => 'int',
        'status'    => 'int',
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
