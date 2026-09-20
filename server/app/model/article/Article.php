<?php

declare(strict_types=1);

namespace app\model\article;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 文章（articles 表）。由代码生成器生成，可直接手改。 */
class Article extends Model
{
    use SoftDeletes;

    protected $table = 'articles';

    /** @var array<string, string> */
    protected $casts = [
        'id'          => 'int',
        'category_id' => 'int',
        'tags'        => 'array',
        'view_count'  => 'int',
        'status'      => 'int',
        'publish_at'  => 'datetime',
        'created_by'  => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
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
