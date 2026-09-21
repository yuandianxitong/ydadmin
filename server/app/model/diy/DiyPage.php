<?php

declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 装修页面（diy_pages 表）。 */
class DiyPage extends Model
{
    use SoftDeletes;

    protected $table = 'diy_pages';

    /** @var array<string, string> */
    protected $casts = [
        'id'                   => 'int',
        'components_draft'     => 'array',
        'components_published' => 'array',
        'page_settings'        => 'array',
        'status'               => 'int',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'deleted_at'           => 'datetime',
    ];
}
