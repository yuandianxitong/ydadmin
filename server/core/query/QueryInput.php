<?php

declare(strict_types=1);

namespace core\query;

use core\exception\ValidationException;

final class QueryInput
{
    /**
     * 只对显式声明的可选筛选字段处理空字符串；保留 0、数组和非法类型交给验证器。
     * @param array<string, mixed> $input
     * @param list<string> $optionalFields
     * @return array<string, mixed>
     */
    public static function normalize(array $input, array $optionalFields): array
    {
        foreach ($optionalFields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            if (is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
            if ($input[$field] === '' || $input[$field] === null) {
                unset($input[$field]);
            }
        }

        return $input;
    }

    /**
     * 在 date 规则校验后调用，允许单侧区间，双侧区间禁止结束早于开始。
     * @param array<string, mixed> $input
     * @param list<array{0: string, 1: string}> $ranges
     */
    public static function assertDateRanges(array $input, array $ranges): void
    {
        foreach ($ranges as [$start, $end]) {
            if (!isset($input[$start], $input[$end]) || !is_string($input[$start]) || !is_string($input[$end])) {
                continue;
            }
            if (strtotime($input[$end]) < strtotime($input[$start])) {
                throw new ValidationException([$end => lang('validation.after_or_equal', ['attribute' => $end, 'date' => $start])]);
            }
        }
    }
}
