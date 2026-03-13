<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use think\Validate;

class SystemConfigValidate extends Validate
{
    protected $rule = [
        'config_value' => 'require',
        'configs' => 'require|array'
    ];

    protected $message = [
        'config_value.require' => 'validation.config_value_require',
        'configs.require' => 'validation.configs_require',
        'configs.array' => 'validation.configs_array'
    ];

    protected $scene = [
        'update' => ['config_value'],
        'batchUpdate' => ['configs']
    ];
}
