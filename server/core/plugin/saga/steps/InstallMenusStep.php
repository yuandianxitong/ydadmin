<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\contracts\PluginMenus;
use core\saga\SagaContext;

/**
 * install saga：App 插件写菜单模板 + 复制到已授权租户。
 *
 * 位置：排在 `MarkEnabledStep` 之后、`ReloadStep` 之前。菜单是 DB 行、不需要新代码
 * 被 worker 加载；放在 Reload 之前，失败时补偿发生在 worker 还没接手插件之前，
 * 回滚面最小。
 */
final class InstallMenusStep extends AbstractPluginSagaStep
{
    public function __construct(
        array $saga,
        private readonly PluginMenus $menus,
    ) {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'InstallMenus';
    }

    public function execute(SagaContext $ctx): void
    {
        $this->menus->install($this->pluginId(), $this->code(), $ctx);
    }

    public function compensate(SagaContext $ctx): void
    {
        $this->menus->compensateInstall($this->pluginId(), $this->code(), $ctx);
    }
}
