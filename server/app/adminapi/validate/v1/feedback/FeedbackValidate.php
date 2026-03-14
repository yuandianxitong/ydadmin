<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\feedback;

use core\base\Validate;

class FeedbackValidate extends Validate
{
    protected $rule = [
        'id'    => 'require|integer|>:0',
        'reply' => 'require|length:1,2000',
    ];

    protected $message = [
        'id.require'    => 'validation.id_require',
        'reply.require' => 'validation.reply_require',
        'reply.length'  => 'validation.reply_length',
    ];

    protected $scene = [
        'reply' => ['id', 'reply'],
    ];
}
