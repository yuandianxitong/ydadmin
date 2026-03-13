<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
namespace app\validate\common;

use core\base\Validate;

class BaseValidate extends Validate
{
    // 公共验证规则
    protected $commonRules = [
        'id' => 'require|integer|>:0',
        'page' => 'integer|>:0',
        'limit' => 'integer|between:1,100',
        'status' => 'integer|in:0,1',
    ];

    // 公共错误信息
    protected $commonMessages = [
        'id.require' => 'ID不能为空',
        'id.integer' => 'ID必须是整数',
        'page.integer' => '页码必须是整数',
        'limit.between' => '每页数量必须在1-100之间',
    ];

    /**
     * 合并规则
     */
    protected function mergeRules(array $rules): array
    {
        return array_merge($this->commonRules, $rules);
    }

    /**
     * 合并错误信息
     */
    protected function mergeMessages(array $messages): array
    {
        return array_merge($this->commonMessages, $messages);
    }
}
