<?php

declare(strict_types=1);

namespace app\model\catalog;

use core\base\Model;

/** 生成器夹具表二（无状态列/图片列/创建人列）（gen_categories 表）。由代码生成器生成，可直接手改。 */
class GenCategory extends Model
{
    protected $table = 'gen_categories';

    /** @var array<string, string> */
    protected $casts = [
        'id'          => 'int',
        'is_featured' => 'boolean',
        'settings'    => 'array',
        'sort'        => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
