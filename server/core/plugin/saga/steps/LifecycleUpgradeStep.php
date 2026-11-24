<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\PluginLifecycleInvoker;
use core\plugin\PluginLoader;
use core\saga\SagaContext;
use support\Log;

/**
 * upgrade saga 第 6 步：调 manifest.lifecycle 的 upgrade($from, $to) 钩子。
 *
 * 排在 SqlUpgradeStep 之后：钩子抛错不得撤销已提交的 SQL，这由步骤顺序保证，不靠事务。
 */
final class LifecycleUpgradeStep extends AbstractPluginSagaStep
{
    public function __construct(array $saga, private readonly PluginLoader $pluginLoader)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'LifecycleUpgrade';
    }

    /**
     * 必须先 registerPsr4()：saga 子进程（宿主关掉了插件预加载）没装载过任何插件，不注册的话 class_exists() 恒假，
     * 钩子会被当成“没有 lifecycle”静默跳过。注册的是刚换进来的新版源码树，知道怎么迁移的正是新版代码。
     */
    public function execute(SagaContext $ctx): void
    {
        /** @var array<string, mixed> $manifest */
        $manifest = (array) $ctx->get('manifest', []);
        $cls = (string) ($manifest['lifecycle'] ?? '');
        if ($cls === '') {
            return;
        }

        $this->pluginLoader->registerPsr4($manifest, $this->pluginDir());

        // PSR-4 已注册仍找不到类，说明 manifest 声明的 lifecycle 装载不到，是插件配置错误。
        // 不抛（不该为此回滚整个升级），但要留下可检索的痕迹。
        if (!class_exists($cls)) {
            Log::warning(
                '[plugin-lifecycle] manifest 声明了 lifecycle 类，但注册 PSR-4 之后仍然装载不到，'
                . '本次升级钩子未执行（多半是 psr4 前缀/类名/文件位置不匹配）',
                [
                    'plugin_code'     => $this->code(),
                    'lifecycle_class' => $cls,
                    'plugin_dir'      => $this->pluginDir(),
                    'psr4'            => (array) ($manifest['psr4'] ?? []),
                ]
            );

            return;
        }
        (new PluginLifecycleInvoker())->callOn(
            $cls,
            $manifest,
            'upgrade',
            [(string) $ctx->get('from_version', ''), (string) $ctx->get('to_version', '')]
        );
        $ctx->set('lifecycle_upgrade_invoked', true);
    }

    /**
     * 不补偿：LifecycleHook 没有降级钩子，钩子写下的数据留在库里。也不能补调 uninstall，
     * 升级失败后插件仍在用，调 uninstall 会清掉业务数据。
     */
    public function compensate(SagaContext $ctx): void
    {
    }
}
