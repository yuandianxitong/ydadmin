<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use core\base\Validate;

class PermissionValidate extends Validate
{
    protected $rule = [
        'name' => 'require|length:2,100|unique:permissions',
        'title' => 'require|length:2,100',
        'group' => 'require|length:2,50',
        'description' => 'max:500',
        'guard_name' => 'length:2,50',
        'status' => 'integer|in:0,1',
        'sort' => 'integer|>=:0',
    ];

    protected $message = [
        'name.require' => 'validation.perm_name_require',
        'name.length' => 'validation.perm_name_length',
        'name.unique' => 'validation.perm_name_unique',
        'title.require' => 'validation.perm_title_require',
        'title.length' => 'validation.perm_title_length',
        'group.require' => 'validation.perm_group_require',
        'group.length' => 'validation.perm_group_length',
        'description.max' => 'validation.perm_desc_max',
        'guard_name.length' => 'validation.guard_name_length',
        'status.in' => 'validation.status_invalid',
        'sort.integer' => 'validation.sort_integer',
        'sort.>=' => 'validation.sort_min',
    ];

    protected $scene = [
        'create' => ['name', 'title', 'group', 'description', 'guard_name', 'status', 'sort'],
        'update' => ['name', 'title', 'group', 'description', 'guard_name', 'status', 'sort'],
    ];
}
