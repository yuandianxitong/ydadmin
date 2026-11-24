<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\contracts\PluginStateListener;
use core\plugin\contracts\PluginStore;
use core\plugin\contracts\RouteGenerationStore;
use core\plugin\PluginStatus;
use core\plugin\saga\PluginSagaState;
use core\saga\SagaContext;
use support\Db;
use support\Log;

/**
 * uninstall/enable/disable 共用的 saga 第 1 步。
 *
 * execute() 刻意只记账：状态已在 HTTP 请求内写好，saga 只负责让路由真正上下线；真正的动作在 compensate()。
 * 补偿必须条件写回（仍停在本次目标态才写回 previous_status）：排队期间行可能已被另一个操作改走，
 * 无条件覆盖会抹掉别人已生效的操作。
 */
final class RestoreStatusStep extends AbstractPluginSagaStep
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
        return 'RestoreStatus';
    }

    /** 只记账，见类头。 */
    public function execute(SagaContext $ctx): void
    {
        $ctx->set('previous_status', (int) ($this->payload()['previous_status'] ?? 0));
        $ctx->set('status_changed', true);
    }

    /**
     * 仅当这一行仍停在本次操作写下的目标态时，才写回 `previous_status`。
     */
    public function compensate(SagaContext $ctx): void
    {
        if ($ctx->get('status_changed') !== true) {
            // execute() 从未跑过，没有可回滚的事实。
            return;
        }

        // 影响行数为 1，且旧目标态与 previous_status 对 ENABLED 的归属不同，才加版本号。
        $targetStatus = $this->targetStatus();
        $previous = (int) $ctx->get('previous_status');
        $affected = Db::connection()->transaction(function () use ($targetStatus, $previous): int {
            $rows = $this->pluginRepo->updateWhere(
                ['id' => $this->pluginId(), 'status' => $targetStatus],
                ['status' => $previous]
            );
            if ($rows === 1 && $this->generation->shouldBump($targetStatus, $previous, false)) {
                $this->generation->bump();
            }

            return $rows;
        });

        if ($affected === 0) {
            Log::warning(sprintf(
                '[plugin-saga] 插件 %d 生效校验失败后回滚状态影响 0 行：该行已不在本次操作写下的'
                . '目标态（排队窗口内被别的操作改走），本次补偿未改动它的状态',
                $this->pluginId()
            ));

            return;
        }

        $this->stateListener->statusChanged();
    }

    /** 本次操作原本要把 `plugins.status` 推成的目标态——写回判据只认这一个值。 */
    private function targetStatus(): int
    {
        return match ((string) ($this->saga['operation'] ?? '')) {
            PluginSagaState::OP_UNINSTALL => PluginStatus::UNINSTALLED,
            PluginSagaState::OP_ENABLE    => PluginStatus::ENABLED,
            PluginSagaState::OP_DISABLE   => PluginStatus::DISABLED,
            default => throw new BusinessException(
                'RestoreStatusStep 不认识这个 operation：' . (string) ($this->saga['operation'] ?? ''),
                500
            ),
        };
    }
}
