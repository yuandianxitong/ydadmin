<?php

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\PluginMenus;
use support\Container;

/** 插件 Lifecycle 直接调用的入口，签名不能改。 */
final class PluginMenuSync
{
    public static function sync(string $pluginCode, ?int $pluginId = null, ?array $manifest = null, ?int $tenantId = null): void
    {
        Container::get(PluginMenus::class)->sync($pluginCode, $pluginId, $manifest, $tenantId);
    }
}
