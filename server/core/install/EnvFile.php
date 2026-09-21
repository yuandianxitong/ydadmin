<?php

declare(strict_types=1);

namespace core\install;

final class EnvFile
{
    public const MANAGED_KEYS = [
        'APP_DEBUG', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PREFIX',
        'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_DB',
        'JWT_ADMIN_SECRET', 'JWT_USER_SECRET',
    ];

    /** @param array<string, string> $values */
    public function merge(string $path, string $examplePath, array $values): void
    {
        if (!is_file($path)) {
            copy($examplePath, $path);
        }

        $managed = array_fill_keys(self::MANAGED_KEYS, true);
        $seen = [];
        $out = [];
        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m) === 1
                && isset($managed[$m[1]])
                && array_key_exists($m[1], $values)) {
                $out[] = $m[1] . ' = ' . $values[$m[1]];
                $seen[$m[1]] = true;
                continue;
            }
            $out[] = $line;
        }

        while ($out !== [] && $out[array_key_last($out)] === '') {
            array_pop($out);
        }
        foreach (self::MANAGED_KEYS as $key) {
            if (!isset($seen[$key]) && array_key_exists($key, $values)) {
                $out[] = $key . ' = ' . $values[$key];
            }
        }

        file_put_contents($path, implode("\n", $out) . "\n");
    }
}
