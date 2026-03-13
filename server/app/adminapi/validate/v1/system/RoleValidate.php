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

class RoleValidate extends Validate
{
    protected $rule = [
        'name' => 'require|length:2,50|alphaDash|unique:roles',
        'title' => 'require|length:2,100',
        'description' => 'max:500',
        'data_scope' => 'integer|in:1,2,3,4,5',
        'status' => 'integer|in:0,1',
        'sort' => 'integer|>=:0',
        'permission_ids' => 'array',
        'permission_ids.*' => 'integer|>:0',
        'menu_ids' => 'array',
        'menu_ids.*' => 'integer|>:0',
    ];

    protected $message = [
        'name.require' => 'validation.role_name_require',
        'name.length' => 'validation.role_name_length',
        'name.alphaDash' => 'validation.role_name_alpha_dash',
        'name.unique' => 'validation.role_name_unique',
        'title.require' => 'validation.role_title_require',
        'title.length' => 'validation.role_title_length',
        'description.max' => 'validation.role_desc_max',
        'data_scope.in' => 'validation.data_scope_invalid',
        'status.in' => 'validation.status_invalid',
        'sort.integer' => 'validation.sort_integer',
        'sort.>=' => 'validation.sort_min',
        'permission_ids.array' => 'validation.permission_ids_array',
        'permission_ids.*.integer' => 'validation.permission_ids_integer',
        'menu_ids.array' => 'validation.menu_ids_array',
        'menu_ids.*.integer' => 'validation.menu_ids_integer',
    ];

    protected $scene = [
        'create' => ['name', 'title', 'description', 'data_scope', 'status', 'sort', 'permission_ids', 'menu_ids'],
        'update' => ['name', 'title', 'description', 'data_scope', 'status', 'sort'],
    ];
}
