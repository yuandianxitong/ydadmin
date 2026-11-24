<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;
use core\plugin\contracts\PluginStore;

final class PluginRequirements
{
    /** @param array<string, mixed> $manifest */
    public static function coreError(array $manifest, string $coreVersion): ?string
    {
        $core = $manifest['core'] ?? null;
        if (!is_array($core)) {
            return null;
        }
        $min = (string) ($core['min'] ?? '');
        $max = (string) ($core['max'] ?? '');
        $tooLow = $min !== '' && version_compare($coreVersion, $min, '<');
        $tooHigh = $max !== '' && version_compare($coreVersion, $max, '>');
        if (!$tooLow && !$tooHigh) {
            return null;
        }

        $bounds = [];
        if ($min !== '') {
            $bounds[] = "≥{$min}";
        }
        if ($max !== '') {
            $bounds[] = "≤{$max}";
        }
        $code = (string) ($manifest['code'] ?? '');

        return "插件 {$code} 要求核心版本 " . implode(' 且 ', $bounds) . "，当前为 {$coreVersion}";
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<int, string> $activeCodes
     * @return list<string>
     */
    public static function missingDepends(array $manifest, array $activeCodes): array
    {
        $active = array_flip(array_map('strval', $activeCodes));
        $missing = [];
        foreach ((array) ($manifest['depends'] ?? []) as $dep) {
            $dep = (string) $dep;
            if ($dep !== '' && !isset($active[$dep]) && !in_array($dep, $missing, true)) {
                $missing[] = $dep;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<int, string> $activeCodes 平台已启用插件的 code
     */
    public static function assertPlatform(array $manifest, string $coreVersion, array $activeCodes): void
    {
        $coreError = self::coreError($manifest, $coreVersion);
        if ($coreError !== null) {
            throw new BusinessException($coreError, 409);
        }

        $missing = self::missingDepends($manifest, $activeCodes);
        if ($missing !== []) {
            $code = (string) ($manifest['code'] ?? '');
            throw new BusinessException(
                "插件 {$code} 依赖 " . implode(',', $missing) . '，但它们未安装或未启用',
                409
            );
        }
    }

    /** @return list<string> */
    public static function enabledCodes(PluginStore $store): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) ($row['code'] ?? ''),
            $store->enabledRows()
        ));
    }
}
