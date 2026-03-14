<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\version;

use core\base\Validate;

class AppVersionValidate extends Validate
{
    protected $rule = [
        'platform'     => 'require|in:android,ios,harmony',
        'version'      => 'require|max:20',
        'version_code' => 'require|integer',
    ];

    protected $message = [
        'platform.require'     => 'validation.platform_require',
        'platform.in'          => 'validation.platform_in',
        'version.require'      => 'validation.version_require',
        'version.max'          => 'validation.version_max',
        'version_code.require' => 'validation.version_code_require',
        'version_code.integer' => 'validation.version_code_integer',
    ];

    protected $scene = [
        'create' => ['platform', 'version', 'version_code'],
        'update' => ['platform', 'version', 'version_code'],
    ];
}
