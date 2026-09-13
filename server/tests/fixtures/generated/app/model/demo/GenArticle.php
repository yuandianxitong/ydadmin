<?php

declare(strict_types=1);

namespace app\model\demo;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 生成器夹具表（gen_articles 表）。由代码生成器生成，可直接手改。 */
class GenArticle extends Model
{
    use SoftDeletes;

    protected $table = 'gen_articles';

    /** @var array<string, string> */
    protected $casts = [
        'id'           => 'int',
        'price'        => 'string',
        'view_count'   => 'int',
        'published_at' => 'datetime',
        'status'       => 'int',
        'sort'         => 'int',
        'created_by'   => 'int',
        'dept_id'      => 'int',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
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
