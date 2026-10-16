<?php

declare(strict_types=1);

namespace core\validation;

/** 可复用的结构规则；业务授权、记录归属和状态转换仍由 Service 判断。 */
final class CommonRules
{
    /** @return array<string, string> */
    public static function binaryStatus(): array
    {
        return ['status' => 'required|integer|in:0,1'];
    }

    /** @return array<string, string> */
    public static function ids(): array
    {
        return ['ids' => 'required|array|min:1', 'ids.*' => 'integer'];
    }

    /**
     * 分页仍兼容零/负数截断与 limit 上限；非整数由验证器拒绝。
     * @return array<string, string>
     */
    public static function pagination(): array
    {
        return [
            'page' => 'sometimes|integer',
            'limit' => 'sometimes|integer',
            'page_no' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',
        ];
    }
}
