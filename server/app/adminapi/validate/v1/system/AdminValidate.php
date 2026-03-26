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

class AdminValidate extends Validate
{
    protected $rule = [
        'username' => 'require|length:3,20|alphaDash|unique:admins',
        'email' => 'require|email|unique:admins',
        'mobile' => 'mobile',
        'password' => 'require|length:6,20',
        'nickname' => 'length:2,20',
        'avatar' => 'url',
        'department_id' => 'integer',
        'department' => 'max:100',
        'position' => 'max:100',
        'status' => 'integer|in:0,1',
        'role_ids' => 'array',
        'role_ids.*' => 'integer|>:0',
    ];

    protected $message = [
        'username.require' => 'validation.username_require',
        'username.length' => 'validation.username_length_3_20',
        'username.alphaDash' => 'validation.username_alpha_dash',
        'username.unique' => 'validation.username_unique',
        'email.require' => 'validation.email_require',
        'email.email' => 'validation.email_format',
        'email.unique' => 'validation.email_unique',
        'mobile.mobile' => 'validation.mobile_format',
        'password.require' => 'validation.password_require',
        'password.length' => 'validation.password_length',
        'nickname.length' => 'validation.nickname_length',
        'avatar.url' => 'validation.avatar_url',
        'department.max' => 'validation.department_max',
        'position.max' => 'validation.position_max',
        'status.in' => 'validation.status_invalid',
        'role_ids.array' => 'validation.role_ids_array',
        'role_ids.*.integer' => 'validation.role_ids_integer',
    ];

    protected $scene = [
        'create' => ['username', 'email', 'mobile', 'password', 'nickname', 'avatar', 'department_id', 'department', 'position', 'status', 'role_ids'],
    ];

    /**
     * update 场景：去掉 unique 规则，唯一性由 Service 层排除自身后校验
     */
    public function sceneUpdate(): AdminValidate
    {
        return $this->only(['username', 'email', 'mobile', 'nickname', 'avatar', 'department_id', 'department', 'position', 'status', 'role_ids'])
            ->remove('username', 'require|unique')
            ->remove('email', 'require|unique');
    }

    /**
     * 验证手机号
     */
    protected function mobile($value, $rule, $data = [], $field = '', $title = '')
    {
        if (empty($value)) {
            return true;
        }
        return preg_match('/^1[3-9]\d{9}$/', $value) === 1;
    }
}
