<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\diy;

use core\base\Validate;

class DiyLinkValidate extends Validate
{
    protected $rule = [
        'label' => 'require|max:64',
        'path'  => 'require|max:255',
    ];

    protected $scene = [
        'create' => ['label', 'path'],
        'update' => ['label', 'path'],
    ];
}
