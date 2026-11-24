<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\contracts\PluginSagaStore;
use core\plugin\contracts\PluginStore;
use core\plugin\PluginFingerprint;
use core\saga\SagaContext;
use support\Log;

/**
 * install saga 第 7 步：算出期望指纹并落库，然后向 master 发一次滚动 reload 信号。
 *
 * 常驻内存模型下，已在跑的 worker 要等下一次 bootAll() 才能感知插件变化，没有这一步前面的写库都不生效。
 */
final class ReloadStep extends AbstractPluginSagaStep
{
    /**
     * @param \Closure(): void $signal 发信号的动作。生产实现是
     *        `ReloadSignal::trigger()`，由 `PluginSagaRunner` 统一提供。
     */
    public function __construct(
        array $saga,
        private readonly PluginSagaStore $sagaRepo,
        private readonly PluginStore $pluginRepo,
        private readonly \Closure $signal,
    ) {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'Reload';
    }

    /**
     * 期望指纹必须在发信号之前算好：先发信号的话 worker 可能已经换新，算出的是新旧混合态，验证必然超时。
     * VerifyFingerprintStep 只读 ctx 里这一份，不自己再算。
     */
    public function execute(SagaContext $ctx): void
    {
        $expected = PluginFingerprint::fromDatabase($this->pluginRepo);
        // 写库失败只记日志：这一列只供排查，生效校验读的是 ctx 里的值。
        if (!$this->sagaRepo->setExpectedFingerprint($this->sagaId(), $expected)) {
            Log::warning('[plugin-saga] expected_fingerprint 写入 plugin_sagas 失败，运维排查生效状态时台账这一列会是空的：saga=' . $this->sagaId());
        }
        $ctx->set('expected_fingerprint', $expected);

        ($this->signal)();
        $ctx->set('reload_triggered', true);
    }

    /**
     * 不自己补偿：补偿链跑完后由 PluginSagaRunner::settle() 按补偿后的 DB 态统一 reload + verify 一次，
     * 避免每个 step 各 reload 一轮把 worker 池连抖好几次。reload_triggered 是留给 settle() 的判据。
     */
    public function compensate(SagaContext $ctx): void
    {
    }
}
