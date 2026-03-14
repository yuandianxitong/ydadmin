<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\agreement;

use think\Validate;

class AgreementValidate extends Validate
{
    protected $rule = [
        'title'   => 'require|max:200',
        'code'    => 'require|max:50|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
        'content' => 'require',
    ];

    protected $message = [
        'title.require'   => '协议标题不能为空',
        'title.max'       => '协议标题最多200个字符',
        'code.require'    => '协议标识码不能为空',
        'code.max'        => '协议标识码最多50个字符',
        'code.regex'      => '协议标识码只能包含字母、数字和下划线，且以字母开头',
        'content.require' => '协议内容不能为空',
    ];

    protected $scene = [
        'create' => ['title', 'code', 'content'],
        'update' => ['title', 'content'],
    ];
}
