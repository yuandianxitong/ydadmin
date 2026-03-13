<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\auth;

use core\base\Validate;

class LoginValidate extends Validate
{
    protected $rule = [
        'username' => 'require|length:3,50',
        'password' => 'require|length:6,20',
        'captcha' => 'require|length:4,6',
        'captcha_key' => 'require',
    ];

    protected $message = [
        'username.require' => 'validation.username_require',
        'username.length' => 'validation.username_length_3_50',
        'password.require' => 'validation.password_require',
        'password.length' => 'validation.password_length',
        'captcha.require' => 'validation.captcha_require',
        'captcha.length' => 'validation.captcha_length',
        'captcha_key.require' => 'validation.captcha_expired',
    ];
}
