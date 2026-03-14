<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\region;

use core\base\Validate;

class RegionValidate extends Validate
{
    protected $rule = [
        'name' => 'require|max:50',
        'code' => 'max:20',
    ];

    protected $message = [
        'name.require' => 'validation.name_require',
        'name.max'     => 'validation.name_max',
        'code.max'     => 'validation.code_max',
    ];

    protected $scene = [
        'create' => ['name'],
        'update' => ['name'],
    ];
}
