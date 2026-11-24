<?php

declare(strict_types=1);

namespace core\saga;

/**
 * Saga 中的每一步实现一对正反操作：
 * - execute()    正向：可以抛异常，框架会触发补偿
 * - compensate() 反向：仅在 execute() 已经成功完成后才被 SagaRunner 调用
 *
 * 实现要点：
 * - compensate() 必须幂等：可能因为补偿链上别的 step 抛错被重复触发
 * - compensate() 应尽力而为：内部抛错由 SagaRunner 捕获记账后继续其它 step 的补偿，
 *   不要让一个 step 的清理失败阻断其它清理
 */
interface CompensatableStep
{
    /** 步骤名（日志/审计，以及 completed_steps 的落库标识）。 */
    public function name(): string;

    public function execute(SagaContext $ctx): void;

    /** 仅在 execute() 抛过的步骤不会调；execute() 成功后失败链 → 倒序调到这里。 */
    public function compensate(SagaContext $ctx): void;
}
