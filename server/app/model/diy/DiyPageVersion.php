<?php

declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;

/** 装修页面版本快照（diy_page_versions 表）。无 updated_at / 软删。 */
class DiyPageVersion extends Model
{
    public $timestamps = false;

    protected $table = 'diy_page_versions';

    /** @var array<string, string> */
    protected $casts = [
        'id'            => 'int',
        'page_id'       => 'int',
        'version_no'    => 'int',
        'components'    => 'array',
        'page_settings' => 'array',
        'created_by'    => 'int',
        'created_at'    => 'datetime',
    ];
}
