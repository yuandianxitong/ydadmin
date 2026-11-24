<?php

declare(strict_types=1);

namespace app\model\plugin;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 插件主表（plugins）。 */
class Plugin extends Model
{
    use SoftDeletes;

    protected $table = 'plugins';

    /** @var array<string, string> */
    protected $casts = [
        'depends' => 'array',
        'read_safe_routes' => 'array',
        'manifest' => 'array',
        'status' => 'integer',
    ];
}
