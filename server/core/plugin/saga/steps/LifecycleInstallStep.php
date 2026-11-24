<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\contracts\PluginStore;
use core\plugin\ManifestReader;
use core\plugin\PluginLifecycleInvoker;
use core\plugin\PluginLoader;
use core\saga\SagaContext;
use support\Log;

/**
 * install saga 第 5 步：注册 PSR-4 并调 `manifest.lifecycle` 的 `install()` 钩子。
 */
final class LifecycleInstallStep extends AbstractPluginSagaStep
{
    public function __construct(
        array $saga,
        private readonly PluginStore $pluginRepo,
        private readonly PluginLoader $pluginLoader,
        private readonly PluginLifecycleInvoker $lifecycleInvoker,
    ) {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'LifecycleInstall';
    }

    /**
     * 排在 SQL 迁移之后、ENABLED 写入之前，钩子失败同样走补偿链落 FAILED。
     * 首次安装的插件还没被 bootAll() 装载过，必须先 registerPsr4()，否则钩子会被当成“没有 lifecycle”静默跳过。
     * 只有钩子真的跑成功才置 lifecycle_install_invoked，补偿据此决定是否补调 uninstall。
     */
    public function execute(SagaContext $ctx): void
    {
        $manifest = $this->manifest();
        $this->pluginLoader->registerPsr4($manifest, $this->pluginDir());
        $this->lifecycleInvoker->invoke($manifest, 'install');
        $ctx->set('lifecycle_install_invoked', true);
    }

    /**
     * install 钩子成功过、后续步骤失败时补调一次 uninstall 钩子；uninstall 失败只记日志，不阻断落 FAILED。
     */
    public function compensate(SagaContext $ctx): void
    {
        if ($ctx->get('lifecycle_install_invoked') !== true) {
            return;
        }
        try {
            $this->lifecycleInvoker->invoke($this->manifest(), 'uninstall');
        } catch (\Throwable $inner) {
            Log::warning('[plugin-lifecycle] 安装失败后补偿 uninstall 钩子失败：' . $inner->getMessage());
        }
    }

    /**
     * 读 plugins.manifest 列；saga 期间这一列没有写入方。
     *
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $plugin = $this->pluginRepo->find($this->pluginId()) ?? [];
        return ManifestReader::decodeColumn($plugin['manifest'] ?? null);
    }
}
