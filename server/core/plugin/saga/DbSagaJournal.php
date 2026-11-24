<?php

declare(strict_types=1);

namespace core\plugin\saga;

use core\plugin\contracts\PluginSagaStore;
use core\saga\SagaJournal;
use support\Log;

/**
 * SagaJournal 的生产实现：把 SagaRunner 的回调落到 plugin_sagas。
 *
 * - stepCompensated() 不能清空或改写 completed_steps，它是崩溃恢复重放的唯一依据；
 *   markStepCompleted() 是整列覆盖写，所以先读后写。current_step 写成 compensate:<step>。
 * - 补偿失败信息追加进 error 列，不覆盖之前的记录。
 * - 落库失败只记日志、不重抛，记账不能阻断补偿链。
 */
final class DbSagaJournal implements SagaJournal
{
    public function __construct(private readonly PluginSagaStore $repo)
    {
    }

    public function stepCompleted(int $sagaId, string $step, array $completedSteps, array $state): void
    {
        $this->guard(function () use ($sagaId, $step, $completedSteps, $state): void {
            $this->repo->markStepCompleted($sagaId, $step, $completedSteps, $state);
        }, $sagaId, __FUNCTION__);
    }

    public function compensationStarted(int $sagaId, string $error): void
    {
        $this->guard(function () use ($sagaId, $error): void {
            $this->repo->beginCompensating($sagaId, $error);
        }, $sagaId, __FUNCTION__);
    }

    public function stepCompensated(int $sagaId, string $step, ?string $error, array $state): void
    {
        $this->guard(function () use ($sagaId, $step, $error, $state): void {
            $row = $this->repo->find($sagaId);
            $raw = (string) ($row['completed_steps'] ?? '');
            $completedSteps = $raw === '' ? [] : (array) json_decode($raw, true);

            $this->repo->markStepCompleted($sagaId, 'compensate:' . $step, $completedSteps, $state);

            if ($error !== null) {
                $this->repo->appendError($sagaId, sprintf('补偿失败[%s]: %s', $step, $error));
            }
        }, $sagaId, __FUNCTION__);
    }

    private function guard(\Closure $fn, int $sagaId, string $callback): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error(sprintf(
                '[saga] journal 写库失败：sagaId=%d callback=%s error=%s',
                $sagaId,
                $callback,
                $e->getMessage()
            ));
        }
    }
}
