<?php

declare(strict_types=1);

namespace core\response;

/** schema 同时定义输出字段与文档；字段类型由模型 casts/业务转换保证。 */
abstract class JsonResource
{
    /** @return array<string, mixed> OpenAPI 3.0 的 data 对象结构 */
    abstract public static function schema(): array;

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function make(array $row): array
    {
        return array_intersect_key($row, static::schema()['properties']);
    }

    /**
     * @param array{list: array<int, array<string, mixed>>, pagination: array<string, int>} $page
     * @return array{list: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public static function page(array $page): array
    {
        return ['list' => array_map(static::make(...), $page['list']), 'pagination' => $page['pagination']];
    }
}
