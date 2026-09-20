<?php

declare(strict_types=1);

namespace app\model\agreement;

use core\base\Model;

/** 协议（agreements 表）。由代码生成器生成，可直接手改。 */
class Agreement extends Model
{
    protected $table = 'agreements';

    /** @var array<string, string> */
    protected $casts = [
        'id'         => 'int',
        'status'     => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
