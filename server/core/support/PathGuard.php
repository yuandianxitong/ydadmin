<?php

declare(strict_types=1);

namespace core\support;

final class PathGuard
{
    public static function within(string $root, string $path): void
    {
        $root = rtrim($root, '/');
        if (!str_starts_with($path, $root . '/') || str_contains($path, '/../') || str_contains($path, '/./')) {
            throw new \RuntimeException('生成路径越出项目目录');
        }
        $relative = substr($path, strlen($root) + 1);
        $current = $root;
        foreach (explode('/', $relative) as $part) {
            $current .= '/' . $part;
            if (is_link($current)) {
                throw new \RuntimeException('生成路径不允许符号链接');
            }
        }
    }
}
