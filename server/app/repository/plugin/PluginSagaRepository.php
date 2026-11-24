<?php

declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\PluginSaga;
use core\base\Model;
use core\base\Repository;
use core\context\RequestContext;
use core\plugin\contracts\PluginSagaStore;

final class PluginSagaRepository extends Repository implements PluginSagaStore
{
    protected bool $dataScoped = false;

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new PluginSaga();
    }

    public function createPending(int $pluginId, string $code, string $operation, array $payload): array
    {
        $traceId = RequestContext::traceId();
        if ($traceId !== '') {
            $payload['trace_id'] = $traceId;
        }

        return $this->create([
            'plugin_id' => $pluginId,
            'code' => $code,
            'operation' => $operation,
            'status' => PluginSaga::ST_PENDING,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function hasActive(int $pluginId): bool
    {
        return $this->query()
            ->where('plugin_id', $pluginId)
            ->whereIn('status', PluginSaga::ACTIVE_STATUSES)
            ->exists();
    }

    public function setExpectedFingerprint(int $sagaId, string $fingerprint): bool
    {
        return $this->update($sagaId, ['expected_fingerprint' => $fingerprint]);
    }

    public function markStepCompleted(int $sagaId, string $step, array $completedSteps, array $stepState): bool
    {
        return $this->update($sagaId, [
            'current_step' => $step,
            'completed_steps' => json_encode(array_values($completedSteps), JSON_UNESCAPED_UNICODE),
            'step_state' => json_encode($stepState, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function beginCompensating(int $sagaId, string $error): bool
    {
        return $this->update($sagaId, [
            'status' => PluginSaga::ST_COMPENSATING,
            'error' => mb_substr($error, 0, 1000),
        ]);
    }

    public function appendError(int $sagaId, string $message): bool
    {
        $row = $this->find($sagaId);
        $existing = trim((string) ($row['error'] ?? ''));
        $combined = $existing === '' ? $message : $existing . '; ' . $message;

        return $this->update($sagaId, ['error' => mb_substr($combined, 0, 1000)]);
    }

    public function finish(int $sagaId, string $status, ?string $error = null, ?string $observedFingerprint = null): bool
    {
        $data = ['status' => $status, 'finished_at' => date('Y-m-d H:i:s')];
        if ($error !== null) {
            $data['error'] = mb_substr($error, 0, 1000);
        }
        if ($observedFingerprint !== null) {
            $data['observed_fingerprint'] = $observedFingerprint;
        }

        return $this->update($sagaId, $data);
    }
}
