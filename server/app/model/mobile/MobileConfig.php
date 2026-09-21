<?php

declare(strict_types=1);

namespace app\model\mobile;

use core\base\Model;

/** 移动端配置（mobile_configs 表，全站一行）。无软删。 */
class MobileConfig extends Model
{
    protected $table = 'mobile_configs';

    /** @var array<string, string> */
    protected $casts = [
        'id'           => 'int',
        'theme_colors' => 'array',
        'tabbar_json'  => 'array',
        'tabbar_style' => 'array',
        'status'       => 'int',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
