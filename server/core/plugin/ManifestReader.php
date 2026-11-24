<?php

declare(strict_types=1);

namespace core\plugin;

final class ManifestReader
{
    public static function path(string $pluginDir): string
    {
        return rtrim($pluginDir, '/') . '/plugin.json';
    }

    public static function tryRead(string $pluginDir): ?array
    {
        $file = self::path($pluginDir);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    public static function decodeColumn(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        $data = json_decode((string) $raw, true);

        return is_array($data) ? $data : [];
    }
}
