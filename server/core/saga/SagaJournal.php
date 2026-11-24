<?php

declare(strict_types=1);

namespace core\saga;

/**
 * Saga 每一步的落库回调。`SagaRunner` 只管顺序执行/逆序补偿，把"写哪张表"
 * 的决定权交给实现者——生产实现见 `core\plugin\saga\DbSagaJournal`
 * （落到 `plugin_sagas`），单测里用内存假实现验证编排逻辑本身。
 */
interface SagaJournal
{
    /**
     * 某一步正向执行成功后立刻调用。进程被 SIGKILL 时，DB 里必须已经有
     * "做到哪一步、补偿需要知道什么"——这是持久化 saga 台账存在的全部理由。
     *
     * @param array<int, string> $completedSteps
     * @param array<string, mixed> $state
     */
    public function stepCompleted(int $sagaId, string $step, array $completedSteps, array $state): void;

    /** 某一步执行失败、即将开始逆序补偿时调用一次。 */
    public function compensationStarted(int $sagaId, string $error): void;

    /**
     * 某一步补偿完成（成功或失败）后调用。`$error` 为 null 表示该步补偿成功。
     *
     * @param array<string, mixed> $state
     */
    public function stepCompensated(int $sagaId, string $step, ?string $error, array $state): void;
}
