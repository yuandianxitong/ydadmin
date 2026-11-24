<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\contracts\PluginStore;
use core\plugin\contracts\RouteGenerationStore;
use core\plugin\PluginStatus;
use core\saga\SagaContext;
use support\Db;
use support\Log;

/**
 * upgrade saga 第 2 步：确认这一行的“升级中”占位仍属于本次 saga，失败时把它交还。
 *
 * 防并发的原子门在发起请求里；saga 隔着排队窗口执行，行可能已被改走或删除，所以这里重判 DB 现值。
 * 第 1 步失败时本步从未执行，那时由 PluginSagaRunner 的失败路径兜底，保证行不停在 UPGRADING。
 */
final class MarkUpgradingStep extends AbstractPluginSagaStep
{
    public function __construct(
        array $saga,
        private readonly PluginStore $pluginRepo,
        private readonly RouteGenerationStore $generation,
    ) {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'MarkUpgrading';
    }

    public function execute(SagaContext $ctx): void
    {
        $plugin = $this->pluginRepo->find($this->pluginId());
        if ($plugin === null) {
            throw new BusinessException('插件不存在（已在升级排队期间被删除）', 404);
        }
        if ((int) $plugin['status'] !== PluginStatus::UPGRADING) {
            throw new BusinessException(
                sprintf(
                    '插件行已不在本次升级的占位状态（期望 UPGRADING=%d，此刻 %d）：'
                    . '请求里那道原子门的结论只在当时成立，排队窗口内它已被别的操作改走',
                    PluginStatus::UPGRADING,
                    (int) $plugin['status']
                ),
                409
            );
        }
        $ctx->set('upgrading_claimed', true);
    }

    /**
     * 把占位状态交还回去。
     *
     * 源码换不回来（source_restore_failed，由补偿链上更早执行的 BackupCurrentStep 写）时落 FAILED，否则落 ENABLED。
     * 条件更新 WHERE status=UPGRADING：别人已改走的行不能再覆盖。影响 0 行只记日志不抛，
     * 免得次生问题盖过原始异常；PluginSagaRunner 在补偿后还会复核。不写 last_error，由 runner 统一写。
     */
    public function compensate(SagaContext $ctx): void
    {
        if ($ctx->get('upgrading_claimed') !== true) {
            return;
        }
        $target = $ctx->get('source_restore_failed') === true
            ? PluginStatus::FAILED
            : PluginStatus::ENABLED;

        // 目标 ENABLED 且影响行数为 1 才加版本号；目标 FAILED 不加（行已不在 ENABLED）。
        $affected = Db::connection()->transaction(function () use ($target): int {
            $rows = $this->pluginRepo->updateWhere(
                ['id' => $this->pluginId(), 'status' => PluginStatus::UPGRADING],
                ['status' => $target]
            );
            if ($rows === 1 && $this->generation->shouldBump(PluginStatus::UPGRADING, $target, false)) {
                $this->generation->bump();
            }

            return $rows;
        });
        if ($affected === 0) {
            Log::warning(sprintf(
                '[plugin-upgrade] 插件 %d 升级失败后交还占位状态影响 0 行：该行已不在 UPGRADING'
                . '（升级排队/执行期间被别的路径改走），本次补偿没有改动它的状态',
                $this->pluginId()
            ));
        }
    }
}
