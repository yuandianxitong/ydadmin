<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use core\plugin\contracts\PluginMenus;
use core\saga\SagaContext;

/** 站点端的插件不写菜单表：三个方法都不碰库。 */
final class PluginMenuAdapter implements PluginMenus
{
    public function install(int $pluginId, string $code, SagaContext $ctx): void
    {
    }

    public function compensateInstall(int $pluginId, string $code, SagaContext $ctx): void
    {
    }

    public function sync(string $pluginCode, ?int $pluginId = null, ?array $manifest = null, ?int $scopeId = null): void
    {
    }
}
