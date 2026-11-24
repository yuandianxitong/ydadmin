<?php

declare(strict_types=1);

namespace core\plugin;

final class PluginPaths
{
    public static function root(): string
    {
        $root = (string) (config('plugin.root') ?: base_path('plugins'));

        return rtrim($root, '/');
    }

    public static function dir(string $code): string
    {
        return self::root() . '/' . $code;
    }
}
