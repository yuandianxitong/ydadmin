<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\contracts\PluginMenus;
use core\plugin\contracts\PluginStore;
use core\plugin\ManifestReader;
use core\plugin\PluginKind;
use core\saga\SagaContext;
use support\Log;

/**
 * upgrade saga：升级确认生效后、清理备份之前，按新版 manifest 对齐菜单模板与存量租户菜单。
 *
 * 尽力而为、无补偿：菜单同步只增改不删、没有逆操作，为菜单没对齐回滚一次已生效的升级不划算，所以失败只记录不抛。
 */
final class SyncMenusStep extends AbstractPluginSagaStep
{
    public function __construct(
        array $saga,
        private readonly PluginStore $store,
        private readonly PluginMenus $menus,
    ) {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'SyncMenus';
    }

    public function execute(SagaContext $ctx): void
    {
        $plugin = $this->store->find($this->pluginId()) ?? [];
        if ((string) ($plugin['kind'] ?? '') !== PluginKind::APP) {
            return;
        }
        $manifest = ManifestReader::decodeColumn($plugin['manifest'] ?? null);

        try {
            $this->menus->sync($this->code(), $this->pluginId(), $manifest);
        } catch (\Throwable $e) {
            // 失败原因写进 ctx，随 step_state 落库，排查时不必只靠翻日志。
            $ctx->set('menu_sync_error', $e->getMessage());
            Log::warning(sprintf(
                '[plugin-menu-sync] %s 升级后菜单对齐失败（升级本身已生效，不回滚）：%s',
                $this->code(),
                $e->getMessage()
            ));
        }
    }

    /** 只在成功路径上，无需补偿（见类头）。 */
    public function compensate(SagaContext $ctx): void
    {
    }
}
