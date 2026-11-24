<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

/**
 * 插件市场分类码表的读取与归一。码表由调用方传入（来自 PluginHostProfile::categories()），本类不读配置。
 */
final class PluginCategory
{
    public const DEFAULT = 'other';

    /**
     * @param array<string, array{label:string,sort:int}> $categories
     * @return array<string, array{label:string,sort:int}>
     */
    public static function all(array $categories): array
    {
        return $categories;
    }

    /**
     * @param array<string, array{label:string,sort:int}> $categories
     * @return string[]
     */
    public static function keys(array $categories): array
    {
        return array_keys(self::all($categories));
    }

    /** @param array<string, array{label:string,sort:int}> $categories */
    public static function normalize(?string $category, array $categories): string
    {
        $key = (string) $category;
        return array_key_exists($key, self::all($categories)) ? $key : self::DEFAULT;
    }
}
