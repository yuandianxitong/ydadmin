<?php

declare(strict_types=1);

namespace core\plugin\contracts;

use core\saga\SagaContext;

interface PluginMenus
{
    /** 安装流程：落菜单并分发；中途失败时自行回收再抛出 */
    public function install(int $pluginId, string $code, SagaContext $ctx): void;

    /** 安装流程补偿：按 install() 写进上下文的记录回收 */
    public function compensateInstall(int $pluginId, string $code, SagaContext $ctx): void;

    /** 按 manifest 对齐已安装插件的菜单；$scopeId 非空时只对齐这一个范围 */
    public function sync(string $pluginCode, ?int $pluginId = null, ?array $manifest = null, ?int $scopeId = null): void;
}
