<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface PluginSagaStore
{
    public function find(int $id): ?array;

    public function setExpectedFingerprint(int $sagaId, string $fingerprint): bool;

    public function markStepCompleted(int $sagaId, string $step, array $completedSteps, array $stepState): bool;

    public function beginCompensating(int $sagaId, string $error): bool;

    public function appendError(int $sagaId, string $message): bool;

    public function finish(int $sagaId, string $status, ?string $error = null, ?string $observedFingerprint = null): bool;
}
