<?php

declare(strict_types=1);

namespace core\plugin;

final class PluginVersion
{
    public static function isUpgrade(string $from, string $to): bool
    {
        return version_compare($to, $from, '>');
    }
}
