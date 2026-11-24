<?php

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\PluginStore;
use support\Log;

/**
 * 插件运行时入口：worker 启动时扫描插件目录写入 registry，再装载 DB 中已启用的插件。
 *
 * bootAll() 只在 worker 启动时调一次，插件启停要等 worker 重启后才生效；不得在请求路径上调用。
 * 除 $registry 外的构造参数都有默认值，php-di 自动装配会把它们全部置 null、装载链路整体失效，
 * 所以容器必须为本类显式绑定。
 */
class PluginManager
{
    /**
     * bootAll() 收集的、按应用分组的待挂载路由条目。
     * 键为路由分组名（见 RouteBuckets::controllerDirs()），值为
     * PluginLoader::load() 返回的 entry 列表。
     *
     * @var array<string, array<int, array{code: string, entitlement: string}>>
     */
    private array $routesByApp = [];

    /**
     * 本 worker 内真正装载成功的插件（code => version）。
     *
     * @var array<string, string>
     */
    private array $loadedVersions = [];

    public function __construct(
        private readonly PluginScanner $scanner,
        private readonly PluginRegistry $registry,
        private readonly ?PluginLoader $loader = null,
        private readonly ?PluginStore $pluginRepo = null,
        private readonly ?PluginEventRegistrar $eventRegistrar = null,
    ) {
    }

    public function bootAll(): void
    {
        // registry 与 DB 无关，install/upgrade 工具链也要用；无效 manifest 已由 PluginScanner 跳过并记日志。
        $manifestByCode = [];
        foreach ($this->scanner->scanDetailed() as $code => $entry) {
            $manifest = $entry['manifest'];
            $dir = $entry['dir'];

            $this->registry->register($code, [
                'version'  => $manifest['version'] ?? '0.0.0',
                'dir'      => $dir,
                'manifest' => $manifest,
            ]);
            $manifestByCode[$code] = ['manifest' => $manifest, 'dir' => $dir];
        }

        if ($this->loader === null || $this->pluginRepo === null) {
            return;
        }
        try {
            $enabled = $this->pluginRepo->enabledRows();
        } catch (\Throwable $e) {
            // 首次安装前 plugins 表不存在或 DB 暂不可用：只保留 registry。
            Log::warning('[plugin-manager] plugins 表不可用，本次 boot 仅写 registry，不挂载路由：' . $e->getMessage());
            return;
        }

        foreach ($enabled as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '' || !isset($manifestByCode[$code])) {
                continue;
            }

            // 单个插件装载失败（如解压半截、声明的文件缺失）只跳过它，不拖垮其余插件。
            try {
                $entries = $this->loader->load(
                    $manifestByCode[$code]['manifest'],
                    $manifestByCode[$code]['dir'],
                );

                // 只能在 load() 成功之后记账，否则半截插件也会被算进指纹。
                $this->loadedVersions[$code] = (string) ($manifestByCode[$code]['manifest']['version'] ?? '0.0.0');

                foreach ($entries as $appName => $entry) {
                    $this->routesByApp[$appName][] = $entry;
                }

                // 必须在 load() 之后：subscriber/handler 类依赖 PSR-4 挂载。订阅失败不影响路由，只记 warning。
                if ($this->eventRegistrar !== null) {
                    try {
                        $this->eventRegistrar->register($code, $manifestByCode[$code]['manifest']);
                    } catch (\Throwable $e) {
                        Log::warning(sprintf('[plugin:%s] 事件订阅注册失败：%s', $code, $e->getMessage()));
                    }
                }
            } catch (\Throwable $e) {
                Log::error(sprintf(
                    '[plugin:%s] 装载失败，已跳过（不影响其余插件）：%s',
                    $code,
                    $e->getMessage(),
                ));
                continue;
            }
        }
    }

    /**
     * 本 worker 内真正装载成功的插件（code => version）。
     *
     * worker 指纹只能用这个集合算，不能改查 DB 的 ENABLED 集合：装载失败被跳过的插件在 DB 里
     * 仍是 ENABLED，会算出“已生效”的假指纹，让 saga 误判安装成功。
     *
     * @return array<string, string>
     */
    public function loadedVersions(): array
    {
        return $this->loadedVersions;
    }

    /**
     * 空操作：插件路由由 PluginRouteDispatcher 在 Route::fallback 里按请求匹配，不进 FastRoute。
     */
    public function mountRoutesFor(string $appName): void
    {
    }
}
