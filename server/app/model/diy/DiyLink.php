<?php

declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 装修链接库（diy_links 表）。 */
class DiyLink extends Model
{
    use SoftDeletes;

    protected $table = 'diy_links';

    /** @var array<string, string> */
    protected $casts = [
        'id'         => 'int',
        'sort'       => 'int',
        'status'     => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
