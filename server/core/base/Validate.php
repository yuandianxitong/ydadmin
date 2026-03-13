<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\base;

use think\Validate as BaseValidate;

abstract class Validate extends BaseValidate
{
    // 公共验证规则
    protected $commonRules = [
        'id' => 'require|integer|>:0',
        'page' => 'integer|>:0',
        'limit' => 'integer|between:1,100',
        'status' => 'integer|in:0,1',
        'sort' => 'integer|>=:0',
    ];

    // 公共错误信息（使用语言包 key，构造函数中自动翻译）
    protected $commonMessages = [
        'id.require' => 'validation.id_require',
        'id.integer' => 'validation.id_integer',
        'id.>' => 'validation.id_gt_zero',
        'page.integer' => 'validation.page_integer',
        'page.>' => 'validation.page_gt_zero',
        'limit.integer' => 'validation.limit_integer',
        'limit.between' => 'validation.limit_between',
        'status.integer' => 'validation.status_integer',
        'status.in' => 'validation.status_invalid',
        'sort.integer' => 'validation.sort_integer',
        'sort.>=' => 'validation.sort_min',
    ];

    public function __construct()
    {
        parent::__construct();
        // 自动将 message 和 commonMessages 中的语言包 key 翻译为实际文本
        foreach ($this->commonMessages as $k => $v) {
            if (is_string($v)) {
                $this->commonMessages[$k] = lang($v);
            }
        }
        foreach ($this->message as $k => $v) {
            if (is_string($v)) {
                $this->message[$k] = lang($v);
            }
        }
    }

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

    /**
     * 验证唯一性（排除自身）
     * 与父类签名保持一致
     */
    public function unique($value, $rule, array $data = [], string $field = ''): bool
    {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }

        $table = $rule[0] ?? '';
        // 若未显式传入字段名，则使用规则中的第二项作为字段名
        $field = $field ?: ($rule[1] ?? $field);
        $pk = $rule[2] ?? 'id';
        $id = $data[$pk] ?? 0;

        if ($table === '' || $field === '') {
            // 缺少必要信息时，返回 true 避免误判（或抛出异常也可）
            return true;
        }

        $map = [$field => $value];
        if ($id) {
            $map[] = [$pk, '<>', $id];
        }

        return !db($table)->where($map)->find();
    }
}
