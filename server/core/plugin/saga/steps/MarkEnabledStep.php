<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\contracts\PluginStateListener;
use core\plugin\contracts\PluginStore;
use core\plugin\contracts\RouteGenerationStore;
use core\plugin\ManifestReader;
use core\plugin\PluginStatus;
use core\saga\SagaContext;
use support\Db;

/**
 * install saga 第 6 步：把 `plugins` 行标记为 ENABLED（含 depends/entitlement
 * 两列）并清空全局权益缓存。
 */
final class MarkEnabledStep extends AbstractPluginSagaStep
{
    public function __construct(
        array $saga,
        private readonly PluginStore $pluginRepo,
        private readonly PluginStateListener $stateListener,
        private readonly RouteGenerationStore $generation,
    ) {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'MarkEnabled';
    }

    /**
     * 必须作为可补偿链上的一步：这里写库失败时，前面已发布的产物要逆序回收、行落 FAILED，
     * 否则行会锁死在 INSTALLING。depends 与 entitlement 两列必须在同一次 update() 里写，
     * 依赖保护和按权益查插件都靠它们。
     */
    public function execute(SagaContext $ctx): void
    {
        $plugin = $this->pluginRepo->find($this->pluginId()) ?? [];
        $manifest = ManifestReader::decodeColumn($plugin['manifest'] ?? null);
        $depends = array_values(array_filter(array_map('strval', (array) ($manifest['depends'] ?? []))));

        // 状态写入与版本号加一同一事务：进入 ENABLED 是安装最终态，必加一。
        $before = (int) ($plugin['status'] ?? PluginStatus::INSTALLING);
        Db::connection()->transaction(function () use ($before, $depends, $manifest): void {
            $this->pluginRepo->update($this->pluginId(), [
                ...$this->stateListener->enabledColumns($this->code(), $manifest),
                'status'       => PluginStatus::ENABLED,
                'installed_at' => date('Y-m-d H:i:s'),
                'last_error'   => '',
                'depends'      => $depends !== [] ? json_encode($depends, JSON_UNESCAPED_UNICODE) : null,
            ]);
            if ($this->generation->shouldBump($before, PluginStatus::ENABLED, true)) {
                $this->generation->bump();
            }
        });

        // 排在 ENABLED 落库之后：写库失败时不该清缓存。
        $this->stateListener->statusChanged();
    }

    /**
     * 撤销本 step 写下的 ENABLED，落 FAILED：停在 INSTALLING 会锁死这一行（upload() 只接受 FAILED 行重传）。
     * 本 step 从未执行过的失败由 PluginSagaRunner::handle() 兜底落 FAILED，两处缺一不可。
     * 不写 last_error：补偿期拿不到原始异常，由 runner 在补偿后统一写。
     */
    public function compensate(SagaContext $ctx): void
    {
        // 离开 ENABLED（也是安装最终态 FAILED），与状态写入同一事务加一。
        $before = (int) ($this->pluginRepo->find($this->pluginId())['status'] ?? PluginStatus::ENABLED);
        Db::connection()->transaction(function () use ($before): void {
            $this->pluginRepo->update($this->pluginId(), ['status' => PluginStatus::FAILED]);
            if ($this->generation->shouldBump($before, PluginStatus::FAILED, true)) {
                $this->generation->bump();
            }
        });
        $this->stateListener->statusChanged();
    }
}
