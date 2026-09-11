<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 数据字典（dictionaries 表，契约 §2.6）。有 deleted_at → SoftDeletes；不受数据权限约束。 */
class Dictionary extends Model
{
    use SoftDeletes;

    protected $table = 'dictionaries';

    /** @var array<string, string> */
    protected $casts = [
        'status' => 'integer',
        'sort'   => 'integer',
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

    /**
     * 字典项，sort、id 升序（SoftDeletes 作用域自动排除已删除的项）。
     *
     * @return HasMany<DictionaryItem, $this>
     */
    public function items(): HasMany
    {
        $relation = $this->hasMany(DictionaryItem::class, 'dictionary_id');
        $relation->orderBy('sort')->orderBy('id');

        return $relation;
    }
}
