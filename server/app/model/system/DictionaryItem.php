<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 字典项（dictionary_items 表）。(dictionary_id, value) 唯一，唯一索引对软删行同样生效。 */
class DictionaryItem extends Model
{
    use SoftDeletes;

    protected $table = 'dictionary_items';

    /** @var array<string, string> */
    protected $casts = [
        'dictionary_id' => 'integer',
        'status'        => 'integer',
        'sort'          => 'integer',
    ];

    /** @var list<string> */
    protected $appends = ['status_text'];

    public function getStatusTextAttribute(): string
    {
        if (!array_key_exists('status', $this->attributes)) {
            return '';
        }

        return (int) $this->attributes['status'] === 1 ? '正常' : '禁用';
    }
}
