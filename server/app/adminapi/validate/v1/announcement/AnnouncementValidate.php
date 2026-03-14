<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\announcement;

use core\base\Validate;

class AnnouncementValidate extends Validate
{
    protected $rule = [
        'title'   => 'require|max:200',
        'content' => 'require',
        'type'    => 'require|in:1,2,3',
    ];

    protected $message = [
        'title.require'   => 'validation.title_require',
        'title.max'       => 'validation.title_max',
        'content.require' => 'validation.content_require',
        'type.require'    => 'validation.type_require',
        'type.in'         => 'validation.type_invalid',
    ];

    protected $scene = [
        'create' => ['title', 'content', 'type'],
        'update' => ['title', 'content'],
    ];
}
