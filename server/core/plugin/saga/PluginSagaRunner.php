<?php

declare(strict_types=1);

namespace core\plugin\saga;

use core\context\RequestContext;
use core\exception\BusinessException;
use core\plugin\contracts\PluginMenus;
use core\plugin\contracts\PluginSagaStore;
use core\plugin\contracts\PluginStateListener;
use core\plugin\contracts\PluginStore;
use core\plugin\contracts\RouteGenerationStore;
use core\plugin\PluginDomainEvent;
use core\plugin\PluginFingerprint;
use core\plugin\PluginLifecycleInvoker;
use core\plugin\PluginLoader;
use core\plugin\PluginManifestValidator;
use core\plugin\PluginPackageInstaller;
use core\plugin\PluginSqlRunner;
use core\plugin\PluginStatus;
use core\plugin\ReloadSignal;
use core\plugin\saga\steps\BackupCurrentStep;
use core\plugin\saga\steps\CheckRequirementsStep;
use core\plugin\saga\steps\CleanupUpgradeStep;
use core\plugin\saga\steps\ExtractZipStep;
use core\plugin\saga\steps\HostSdkCompatStep;
use core\plugin\saga\steps\InspectAndValidateStep;
use core\plugin\saga\steps\InstallMenusStep;
use core\plugin\saga\steps\LifecycleInstallStep;
use core\plugin\saga\steps\LifecycleUpgradeStep;
use core\plugin\saga\steps\MarkEnabledStep;
use core\plugin\saga\steps\MarkUpgradedStep;
use core\plugin\saga\steps\MarkUpgradingStep;
use core\plugin\saga\steps\PublishAssetsStep;
use core\plugin\saga\steps\ReloadStep;
use core\plugin\saga\steps\RestoreStatusStep;
use core\plugin\saga\steps\SqlInstallStep;
use core\plugin\saga\steps\SqlUpgradeStep;
use core\plugin\saga\steps\SwapNewVersionStep;
use core\plugin\saga\steps\SyncMenusStep;
use core\plugin\saga\steps\VerifyFingerprintStep;
use core\saga\CompensatableStep;
use core\saga\SagaContext;
use core\saga\SagaRunner;
use core\support\ErrorClassifier;
use DI\Attribute\Inject;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcherContract;
use support\Db;
use support\Log;

/**
 * 一条 plugin_sagas 行的执行器：按 operation 组装 step、跑 SagaRunner、收尾写终态，失败时补偿后统一重新生效一次。
 *
 * 由宿主在关掉插件预加载的一次性子进程里调用，子进程不预载插件代码，钩子不会跑到旧版字节码。
 * triggerReload()/reloadVerifier() 是 protected 方法、其余依赖用属性注入，测试子类只需覆写这两处。
 */
class PluginSagaRunner
{
    #[Inject]
    protected PluginSagaStore $sagaRepo;

    #[Inject]
    protected PluginStore $pluginRepo;

    #[Inject]
    protected DbSagaJournal $journal;

    #[Inject]
    protected ReloadVerifier $verifier;

    #[Inject]
    protected PluginSqlRunner $sqlRunner;

    #[Inject]
    protected PluginPackageInstaller $packageInstaller;

    #[Inject]
    protected PluginManifestValidator $manifestValidator;

    #[Inject]
    protected PluginLoader $pluginLoader;

    #[Inject]
    protected PluginLifecycleInvoker $lifecycleInvoker;

    /** 插件路由版本号：saga 的状态写入改变可见性时，与写入同一事务里加一。 */
    #[Inject]
    protected RouteGenerationStore $generation;

    #[Inject]
    protected PluginMenus $menus;

    #[Inject]
    protected PluginStateListener $stateListener;

    /**
     * 进程内共享的事件总线，只在 {@see publishTerminalEvent()} 使用。
     */
    #[Inject]
    protected EventDispatcherContract $dispatcher;

    /**
     * 退出码（与 `plugin:saga:run` 命令同一份契约）：
     *   0 = 成功
     *   1 = 已补偿失败（saga 走完了补偿链，系统回到了一个可用状态，但原操作失败）
     *   2 = 不可恢复（saga 行不存在，或**补偿本身**也失败），需要人工介入
     */
    public function handle(int $sagaId, bool $compensateOnly = false): int
    {
        $saga = $this->sagaRepo->find($sagaId);
        if ($saga === null) {
            Log::error('[plugin-saga] saga 不存在：' . $sagaId);
            return 2;
        }

        $ctx = new SagaContext($sagaId, (array) (json_decode((string) ($saga['step_state'] ?? '{}'), true) ?: []));
        $runner = new SagaRunner($this->journal);

        try {
            // stepsFor() 也在 try 之内：未知 operation 抛出的异常若逃出 handle()，saga 行会永远停在 running。
            $steps = $this->stepsFor($saga);

            if ($compensateOnly) {
                // 崩溃恢复：不续跑，只按持久化的 completed_steps 逆序补偿。没有原始异常可分类，errorCode 传 null。
                $completed = (array) (json_decode((string) ($saga['completed_steps'] ?? '[]'), true) ?: []);
                $runner->compensateCompleted($steps, array_map('strval', $completed), $ctx);
                return $this->settleAndFinish($saga, $ctx, $runner, '进程中断后补偿完成', null);
            }

            $runner->run($steps, $ctx);
            $this->finishSaga($sagaId, PluginSagaState::ST_COMPLETED, null, (string) $ctx->get('observed_fingerprint', ''));
            // 先落库再通知（见 publishTerminalEvent()）。
            $this->publishTerminalEvent($saga, true, null);
            return 0;
        } catch (\Throwable $e) {
            // 只在这里还拿得到原始 \Throwable，就地分类；往下只传分类后的短标识，原始消息只进 saga 台账和日志。
            return $this->settleAndFinish($saga, $ctx, $runner, $e->getMessage(), ErrorClassifier::classify($e));
        }
    }

    /**
     * 失败路径的统一收尾：重新生效 → 落 FAILED → 写 saga 终态 → 算退出码。
     *
     * 收尾期自己出的问题必须写进 plugin_sagas.error，不能只落日志：台账是运维唯一会看的地方。
     *
     * @param array<string, mixed> $saga
     * @param ?string $errorCode ErrorClassifier::classify() 的分类标识，崩溃恢复路径传 null；绝不传原始异常消息
     */
    private function settleAndFinish(array $saga, SagaContext $ctx, SagaRunner $runner, string $error, ?string $errorCode): int
    {
        // 补偿链跑完后统一重新生效一次，期望指纹按补偿后的 DB 态算。
        $settleProblem = $this->settle($ctx);

        // 补偿 step 记下的、运维必须知道但不算补偿失败的事实（退不回的增量、被撤下的产物等）。
        // 补偿期拿不到原始异常，只能经 rollback_notes 由这里统一写进 last_error，所以要先于 failOperation() 算好。
        $notes = implode('', array_map('strval', (array) $ctx->get('rollback_notes', [])));
        $failProblem = $this->failOperation($saga, $error . $notes, $ctx);

        $message = $error . $notes;
        foreach ([$settleProblem, $failProblem] as $problem) {
            if ($problem !== null) {
                $message .= ' | ' . $problem;
            }
        }

        $this->finishSaga(
            (int) $saga['id'],
            PluginSagaState::ST_FAILED,
            $message,
            (string) $ctx->get('observed_fingerprint', '')
        );
        // 先落库再通知（见 publishTerminalEvent()）。
        $this->publishTerminalEvent($saga, false, $errorCode);

        // 退出码 2 的两个来源：补偿链本身出错，或连 FAILED 都没写上。settle() 失败不计入：
        // 那时状态已补偿正确，只是 worker 池还停在旧代码上，且测试环境本来就没有 master。
        return $runner->compensationErrors() === [] && $failProblem === null ? 1 : 2;
    }

    /**
     * 写 saga 终态并判返回值。写库失败只留痕不重抛：异常逃出 handle() 会让行停在 running/compensating，
     * 在 catch 路径上再抛还会盖掉原始失败原因。
     */
    private function finishSaga(int $sagaId, string $status, ?string $error, string $observedFingerprint): void
    {
        try {
            if ($this->sagaRepo->finish($sagaId, $status, $error, $observedFingerprint)) {
                return;
            }
            Log::error(sprintf(
                '[plugin-saga] saga 终态写库影响行数为 0，台账可能停在中间态：sagaId=%d 期望状态=%s',
                $sagaId,
                $status
            ));
        } catch (\Throwable $e) {
            Log::error(sprintf(
                '[plugin-saga] saga 终态写库失败，台账停在中间态需人工介入：sagaId=%d 期望状态=%s error=%s',
                $sagaId,
                $status,
                $e->getMessage()
            ));
        }
    }

    /**
     * 发布 plugin 域终态事件。
     *
     * - 排在 finishSaga() 之后，先落库再通知，否则上游可能收到库里还不是终态的通知；
     * - 只对 install/upgrade 发，其余操作没有上游消费方；
     * - 整段 try/catch：审计上报失败不能把已成功的 saga 变成失败。
     *
     * @param array<string, mixed> $saga
     */
    private function publishTerminalEvent(array $saga, bool $success, ?string $errorCode): void
    {
        $operation = (string) ($saga['operation'] ?? '');
        $event = match (true) {
            $operation === PluginSagaState::OP_INSTALL && $success  => PluginDomainEvent::INSTALLED,
            $operation === PluginSagaState::OP_INSTALL && !$success => PluginDomainEvent::INSTALL_FAILED,
            $operation === PluginSagaState::OP_UPGRADE && $success  => PluginDomainEvent::UPGRADED,
            $operation === PluginSagaState::OP_UPGRADE && !$success => PluginDomainEvent::UPGRADE_FAILED,
            default => null,
        };
        if ($event === null) {
            return;
        }

        try {
            $payload = (array) (json_decode((string) ($saga['payload'] ?? '{}'), true) ?: []);
            $this->dispatcher->dispatch($event, [[
                'code'         => (string) ($saga['code'] ?? ''),
                'from_version' => $payload['from_version'] ?? null,
                'to_version'   => $payload['to_version'] ?? $this->currentPluginVersion((int) $saga['plugin_id']),
                'error_code'   => $errorCode,
                'saga_id'      => (int) $saga['id'],
                'trace_id'     => RequestContext::traceId(),
            ]]);
        } catch (\Throwable $e) {
            Log::warning('[plugin-event] 终态事件发布失败（不影响 saga 结果）：' . $e->getMessage());
        }
    }

    /** 当前 `plugins` 行版本号，供 install 事件的 `to_version` 兜底（install 的 payload 里没有它）。 */
    private function currentPluginVersion(int $pluginId): ?string
    {
        $row = $this->pluginRepo->find($pluginId);
        return $row !== null ? (string) ($row['version'] ?? '') : null;
    }

    /**
     * 按 `operation` 组装 step 序列。
     *
     * @param array<string, mixed> $saga
     * @return array<int, CompensatableStep>
     */
    public function stepsFor(array $saga): array
    {
        $operation = (string) ($saga['operation'] ?? '');

        return match ($operation) {
            PluginSagaState::OP_INSTALL => [
                new CheckRequirementsStep($saga, $this->pluginRepo),
                new ExtractZipStep($saga, $this->packageInstaller),
                new HostSdkCompatStep($saga),
                new PublishAssetsStep($saga),
                new SqlInstallStep($saga, $this->sqlRunner),
                new LifecycleInstallStep($saga, $this->pluginRepo, $this->pluginLoader, $this->lifecycleInvoker),
                new MarkEnabledStep($saga, $this->pluginRepo, $this->stateListener, $this->generation),
                new InstallMenusStep($saga, $this->menus),
                new ReloadStep($saga, $this->sagaRepo, $this->pluginRepo, function (): void {
                    $this->triggerReload();
                }),
                new VerifyFingerprintStep($saga, $this->reloadVerifier()),
            ],
            // 首尾各补一步：InspectAndValidate 在 saga 进程重判请求里的结论；CleanupUpgrade 必须在生效校验之后，
            // 删备份等于交出回滚能力。SyncMenus 在生效之后、清备份之前，自己吞掉失败。
            // 不复用 MarkEnabledStep：它写 installed_at 且补偿落 FAILED，升级失败后插件必须仍可用。
            PluginSagaState::OP_UPGRADE => [
                new InspectAndValidateStep($saga, $this->pluginRepo, $this->packageInstaller, $this->manifestValidator),
                new MarkUpgradingStep($saga, $this->pluginRepo, $this->generation),
                new BackupCurrentStep($saga),
                new SwapNewVersionStep($saga, $this->packageInstaller),
                new SqlUpgradeStep($saga, $this->sqlRunner),
                new LifecycleUpgradeStep($saga, $this->pluginLoader),
                new PublishAssetsStep($saga, true),
                new MarkUpgradedStep($saga, $this->pluginRepo, $this->stateListener, $this->generation),
                new ReloadStep($saga, $this->sagaRepo, $this->pluginRepo, function (): void {
                    $this->triggerReload();
                }),
                new VerifyFingerprintStep($saga, $this->reloadVerifier()),
                new SyncMenusStep($saga, $this->pluginRepo, $this->menus),
                new CleanupUpgradeStep($saga),
            ],
            // 状态已在请求内写好，这里只让路由上下线，生效失败时回滚状态。三个操作的 step 序列相同。
            PluginSagaState::OP_UNINSTALL, PluginSagaState::OP_ENABLE, PluginSagaState::OP_DISABLE => [
                new RestoreStatusStep($saga, $this->pluginRepo, $this->stateListener, $this->generation),
                new ReloadStep($saga, $this->sagaRepo, $this->pluginRepo, function (): void {
                    $this->triggerReload();
                }),
                new VerifyFingerprintStep($saga, $this->reloadVerifier()),
            ],
            // 未知 operation 必须抛错而不是返回空数组：空序列会让 saga 一步
            // 不做就被标成 completed，是最隐蔽的一种"什么都没发生却报告成功"。
            default => throw new BusinessException("未知的 saga operation：{$operation}", 500),
        };
    }

    /**
     * 补偿后的重新生效：只在本次真的动过 worker 能看见的东西时才发信号。
     *
     *   - reload_triggered：信号已经发过，池子正在换成失败版本；
     *   - created_by_this_run：本次解压的目录已被删掉，可能已有 worker 装载了它；
     *   - status_changed：plugins.status 被改过又被补偿改回来了。
     *
     * 失败不改退出码，但要记 Log::error，并返回描述由调用方写进 plugin_sagas.error。
     *
     * @return string|null 收尾问题描述；null 表示没问题（含"本次无需重新生效"）
     */
    private function settle(SagaContext $ctx): ?string
    {
        if ($ctx->get('reload_triggered') !== true
            && $ctx->get('created_by_this_run') !== true
            && $ctx->get('status_changed') !== true) {
            return null;
        }
        try {
            $this->triggerReload();
            $this->reloadVerifier()->waitUntilEffective(PluginFingerprint::fromDatabase($this->pluginRepo));

            return null;
        } catch (\Throwable $e) {
            Log::error('[plugin-saga] 补偿后重新生效失败，需人工介入：' . $e->getMessage());

            return '补偿后重新生效失败，worker 池可能仍停在失败版本上，需人工介入：' . $e->getMessage();
        }
    }

    /**
     * 无论断在哪一步，都要把 plugins 行收尾到可操作的状态，并把原始异常写进 last_error（截断到 1000 字符）。
     *
     * - install 落 FAILED：停在 INSTALLING 会锁死这一行（upload() 只接受 FAILED 行重传）。
     * - upgrade 回到 ENABLED，升级失败后插件必须仍可用；旧版源码没能换回来时落 FAILED。
     *
     * step 的补偿只覆盖已成功执行过的 step，更早的失败只能由这里兜底；只有这里拿得到原始异常文本。
     * 排在补偿链之后执行，写的值最终胜出。
     *
     * @param array<string, mixed> $saga
     * @return string|null 写不上时的问题描述（调用方据此把退出码抬到 2 并写进台账）
     */
    private function failOperation(array $saga, string $error, SagaContext $ctx): ?string
    {
        $operation = (string) ($saga['operation'] ?? '');
        if ($operation === PluginSagaState::OP_UPGRADE) {
            return $this->failUpgrade($saga, $error, $ctx);
        }
        if ($operation !== PluginSagaState::OP_INSTALL) {
            // 状态由 RestoreStatusStep 还原，不能推成 FAILED。仍要写 last_error：它是管理台唯一显示失败原因的地方。
            // 写失败只记日志，不改退出码。
            try {
                $this->pluginRepo->update((int) $saga['plugin_id'], ['last_error' => mb_substr($error, 0, 1000)]);
            } catch (\Throwable $e) {
                Log::error('[plugin-saga] uninstall/enable/disable 失败后写 last_error 失败：' . $e->getMessage());
            }
            return null;
        }
        try {
            // 安装最终态 FAILED：与状态写入同一事务加一。
            $pluginId = (int) $saga['plugin_id'];
            $before = (int) ($this->pluginRepo->find($pluginId)['status'] ?? PluginStatus::INSTALLING);
            Db::connection()->transaction(function () use ($pluginId, $before, $error): void {
                $this->pluginRepo->update($pluginId, [
                    'status'     => PluginStatus::FAILED,
                    'last_error' => mb_substr($error, 0, 1000),
                ]);
                if ($this->generation->shouldBump($before, PluginStatus::FAILED, true)) {
                    $this->generation->bump();
                }
            });

            return null;
        } catch (\Throwable $e) {
            Log::error('[plugin-saga] 安装失败后落 FAILED 状态失败，插件行可能停在 INSTALLING：' . $e->getMessage());

            return '未能把插件行落到 FAILED，它可能停在 INSTALLING（upload() 会 409，需人工介入）：' . $e->getMessage();
        }
    }

    /**
     * upgrade 的失败收尾。last_error 无条件写，即使状态那半因行已被补偿交还而没写上。
     *
     * @param array<string, mixed> $saga
     */
    private function failUpgrade(array $saga, string $error, SagaContext $ctx): ?string
    {
        $target = $ctx->get('source_restore_failed') === true
            ? PluginStatus::FAILED
            : PluginStatus::ENABLED;
        $pluginId = (int) $saga['plugin_id'];

        try {
            // UPGRADING → ENABLED（例如 InspectAndValidate 就失败、MarkUpgrading 从未执行）
            // 回到 ENABLED，影响行数为 1 才加版本号；目标 FAILED 不加。
            $affected = Db::connection()->transaction(function () use ($pluginId, $target): int {
                $rows = $this->pluginRepo->updateWhere(
                    ['id' => $pluginId, 'status' => PluginStatus::UPGRADING],
                    ['status' => $target]
                );
                if ($rows === 1 && $this->generation->shouldBump(PluginStatus::UPGRADING, $target, false)) {
                    $this->generation->bump();
                }

                return $rows;
            });
            $this->pluginRepo->update($pluginId, ['last_error' => mb_substr($error, 0, 1000)]);

            // 0 行是常态（MarkUpgradingStep 的补偿已交还过状态），所以复核现值：仍停在 UPGRADING 说明这一行被锁死了，
            // 必须同时记日志并写进台账。
            if ($affected === 0
                && (int) (($this->pluginRepo->find($pluginId)['status'] ?? 0)) === PluginStatus::UPGRADING) {
                Log::error('[plugin-saga] 升级失败后插件行仍停在 UPGRADING，已被锁死需人工介入：pluginId=' . $pluginId);

                return '插件行仍停在 UPGRADING，upgrade/uninstall/enable/disable/upload 都会拒绝它（需人工介入）';
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('[plugin-saga] 升级失败后复位插件状态失败，插件行可能停在 UPGRADING：' . $e->getMessage());

            return '未能把插件行从 UPGRADING 复位，它可能被锁死在升级中（需人工介入）：' . $e->getMessage();
        }
    }

    /**
     * 向 master 发滚动 reload 信号。测试进程里没有 master，测试子类覆写这里。
     */
    protected function triggerReload(): void
    {
        ReloadSignal::trigger();
    }

    /**
     * 生效校验器。测试进程里没有 worker 写指纹注册表，测试子类覆写这里。
     */
    protected function reloadVerifier(): ReloadVerifier
    {
        return $this->verifier;
    }
}
