<?php

declare(strict_types=1);

namespace core\saga;

use support\Log;

/**
 * M4.8：通用 Saga 编排引擎。
 *
 * 顺序执行步骤；任一 step 抛异常时，按已成功完成的逆序触发补偿；最后把原始异常抛回去。
 * 每一步成功后立刻经 `SagaJournal` 落库，供进程被 SIGKILL 后的崩溃恢复重放。
 *
 * 特性（逐条对齐旧仓 `framework-saas/server/core/saga/SagaRunner.php` 的语义）：
 * - 单 step 的补偿失败仅记账进 compensationErrors，不会阻断其它 step 的清理
 * - 不参与数据库事务 —— 跨多副作用域（DB、文件系统、消息队列），由各 step 自己决定内部事务
 * - 失败 step 本身不会被补偿（它的 execute 没成功，没有副作用可逆）
 *
 * 常驻内存纪律：`$executedSteps`/`$compensationErrors` 是**单次 run()/
 * compensateCompleted() 调用内**的实例状态，不跨请求持有——两个方法开头都会
 * 重置，绝不允许把上一次运行的残留带进下一次。
 */
final class SagaRunner
{
    /** @var array<int, CompensatableStep> 已成功执行的步骤（按入栈顺序） */
    private array $executedSteps = [];

    /** @var array<int, array{step: string, error: string}> 补偿过程中失败的记录 */
    private array $compensationErrors = [];

    public function __construct(private readonly SagaJournal $journal)
    {
    }

    /**
     * @param CompensatableStep[] $steps
     * @throws \Throwable 原始异常透传
     */
    public function run(array $steps, SagaContext $ctx): void
    {
        $this->executedSteps = [];
        $this->compensationErrors = [];
        $completed = [];

        foreach ($steps as $step) {
            try {
                $step->execute($ctx);
            } catch (\Throwable $e) {
                $this->journal->compensationStarted($ctx->sagaId(), $e->getMessage());
                $this->compensateAll($ctx);
                throw $e;
            }
            $this->executedSteps[] = $step;
            $completed[] = $step->name();
            // 每步成功后立刻落库：进程被 SIGKILL 时，DB 里必须已经有"做到哪一步、
            // 补偿需要知道什么"——这是 plugin_sagas 存在的全部理由。
            $this->journal->stepCompleted($ctx->sagaId(), $step->name(), $completed, $ctx->all());
        }
    }

    /**
     * 崩溃恢复用：按 DB 里持久化的已完成步骤名，从完整 step 列表里挑出来逆序补偿。
     *
     * @param CompensatableStep[] $steps
     * @param array<int, string> $completedStepNames
     */
    public function compensateCompleted(array $steps, array $completedStepNames, SagaContext $ctx): void
    {
        $byName = [];
        foreach ($steps as $step) {
            $byName[$step->name()] = $step;
        }
        $this->executedSteps = [];
        $this->compensationErrors = [];
        foreach ($completedStepNames as $name) {
            if (isset($byName[$name])) {
                $this->executedSteps[] = $byName[$name];
                continue;
            }
            // 修复轮 1（Important）：DB 记录的步骤名在当前 step 列表中找不到时，
            // 绝不能无声丢弃——那一步的副作用（已解压的目录、已发布的产物、
            // 已跑的迁移）会永久不被补偿，且没有任何痕迹可查。留日志 + 计入
            // compensationErrors()，让调用方（崩溃恢复流程）能发现"这次恢复
            // 不完整"。这里不抛异常、不中断循环——已知步骤的补偿比报错重要，
            // isset 过滤本身仍要保留。
            Log::warning(sprintf(
                '[saga] 补偿链里的步骤名在当前 step 列表中不存在，已跳过（副作用可能残留）：%s',
                $name
            ));
            $this->compensationErrors[] = [
                'step'  => $name,
                'error' => 'step not found in current step list — compensation skipped',
            ];
        }
        $this->compensateAll($ctx);
    }

    /** @return array<int, array{step: string, error: string}> */
    public function compensationErrors(): array
    {
        return $this->compensationErrors;
    }

    private function compensateAll(SagaContext $ctx): void
    {
        foreach (array_reverse($this->executedSteps) as $step) {
            try {
                $step->compensate($ctx);
                $this->journal->stepCompensated($ctx->sagaId(), $step->name(), null, $ctx->all());
            } catch (\Throwable $e) {
                // 单个 step 的补偿失败不阻断其它 step 的清理（旧仓同款纪律）
                $this->compensationErrors[] = ['step' => $step->name(), 'error' => $e->getMessage()];
                $this->journal->stepCompensated($ctx->sagaId(), $step->name(), $e->getMessage(), $ctx->all());
            }
        }
    }
}
