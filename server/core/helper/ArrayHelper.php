<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\helper;

class ArrayHelper
{
    /**
     * 数组转树形结构
     */
    public static function toTree(array $data, string $idKey = 'id', string $parentKey = 'parent_id', string $childrenKey = 'children', int $parentId = 0): array
    {
        $tree = [];
        foreach ($data as $item) {
            if ((int)$item[$parentKey] == $parentId) {
                $item[$childrenKey] = self::toTree($data, $idKey, $parentKey, $childrenKey, (int)$item[$idKey]);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * 树形结构转数组
     */
    public static function treeToArray(array $tree, string $childrenKey = 'children'): array
    {
        $result = [];
        foreach ($tree as $item) {
            $children = $item[$childrenKey] ?? [];
            unset($item[$childrenKey]);
            $result[] = $item;

            if (!empty($children)) {
                $result = array_merge($result, self::treeToArray($children, $childrenKey));
            }
        }
        return $result;
    }

    /**
     * 数组按指定键分组
     */
    public static function groupBy(array $data, string $key): array
    {
        $result = [];
        foreach ($data as $item) {
            $groupKey = $item[$key] ?? 'default';
            $result[$groupKey][] = $item;
        }
        return $result;
    }

    /**
     * 提取数组中指定列
     */
    public static function column(array $data, string $column, string $indexKey = ''): array
    {
        return array_column($data, $column, $indexKey);
    }

    /**
     * 数组排序
     */
    public static function sortBy(array $data, string $key, bool $desc = false): array
    {
        usort($data, function($a, $b) use ($key, $desc) {
            $result = $a[$key] <=> $b[$key];
            return $desc ? -$result : $result;
        });
        return $data;
    }

    /**
     * 数组过滤
     */
    public static function filter(array $data, \Closure $callback): array
    {
        return array_filter($data, $callback);
    }

    /**
     * 数组去重
     */
    public static function unique(array $data, string $key = ''): array
    {
        if (empty($key)) {
            return array_unique($data);
        }

        $unique = [];
        $seen = [];
        foreach ($data as $item) {
            $value = $item[$key] ?? '';
            if (!in_array($value, $seen)) {
                $seen[] = $value;
                $unique[] = $item;
            }
        }
        return $unique;
    }

    /**
     * 数组分页
     */
    public static function paginate(array $data, int $page = 1, int $limit = 15): array
    {
        $total = count($data);
        $offset = ($page - 1) * $limit;
        $list = array_slice($data, $offset, $limit);

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit)
            ]
        ];
    }

    /**
     * 递归合并数组
     */
    public static function mergeRecursive(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::mergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }
}
