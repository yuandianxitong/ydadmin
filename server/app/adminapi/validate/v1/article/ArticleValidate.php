<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\article;

use core\base\Validate;

class ArticleValidate extends Validate
{
    protected $rule = [
        'title'       => 'require|max:200',
        'category_id' => 'require|integer',
        'content'     => 'require',
    ];

    protected $message = [
        'title.require'       => 'validation.title_require',
        'title.max'           => 'validation.title_max',
        'category_id.require' => 'validation.category_id_require',
        'category_id.integer' => 'validation.category_id_integer',
        'content.require'     => 'validation.content_require',
    ];

    protected $scene = [
        'create' => ['title', 'category_id', 'content'],
        'update' => ['title', 'category_id', 'content'],
    ];
}
