<?php

declare(strict_types=1);

namespace core\query;

final class SortOrder
{
    /**
     * @param list<string> $allowed
     * @return list<array{0: string, 1: string}>
     */
    public static function parse(string $order, array $allowed): array
    {
        $result = [];
        foreach (explode(',', $order) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $segment) ?: [];
            if (count($parts) > 2) {
                throw new \InvalidArgumentException("排序片段格式错误：{$segment}");
            }
            [$column, $direction] = array_pad($parts, 2, 'asc');
            if (!in_array($column, $allowed, true)) {
                throw new \InvalidArgumentException("不允许按 {$column} 排序");
            }
            $result[] = [$column, strtolower($direction) === 'desc' ? 'desc' : 'asc'];
        }

        return $result;
    }
}
