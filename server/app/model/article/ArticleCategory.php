<?php

declare(strict_types=1);

namespace app\model\article;

use core\base\Model;

/** 文章栏目（article_categories 表）。由代码生成器生成，可直接手改。 */
class ArticleCategory extends Model
{
    protected $table = 'article_categories';

    /** @var array<string, string> */
    protected $casts = [
        'id'         => 'int',
        'parent_id'  => 'int',
        'sort'       => 'int',
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
