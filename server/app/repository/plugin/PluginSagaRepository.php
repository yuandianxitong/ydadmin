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

    /** @return list<int> */
    public function activePluginIds(): array
    {
        return array_map(
            'intval',
            $this->query()->whereIn('status', PluginSaga::ACTIVE_STATUSES)->pluck('plugin_id')->all()
        );
    }

    /** 只有还停在 pending 的行能被认领。影响行数为 1 才算抢到。 */
    public function claim(int $sagaId, int $pid): bool
    {
        return $this->updateWhere(
            ['id' => $sagaId, 'status' => PluginSaga::ST_PENDING],
            [
                'status' => PluginSaga::ST_RUNNING,
                'claimed_pid' => $pid,
                'claimed_at' => date('Y-m-d H:i:s'),
            ]
        ) === 1;
    }

    /** @return array<string, mixed>|null */
    public function claimNext(int $pid): ?array
    {
        $candidate = $this->query()->where('status', PluginSaga::ST_PENDING)->orderBy('id')->first();
        if ($candidate === null) {
            return null;
        }
        $id = (int) $candidate->id;
        if (!$this->claim($id, $pid)) {
            return null;
        }

        return $this->find($id);
    }

    /** @return list<array<string, mixed>> */
    public function findRunning(): array
    {
        return $this->query()->where('status', PluginSaga::ST_RUNNING)->orderBy('id')->get()->toArray();
    }

    /**
     * 把认领者已死的 running 行换到本进程名下。改完再读一次，避免「值没变所以影响行数是 0」误判。
     */
    public function reclaimOrphan(int $sagaId, int $deadPid, int $myPid): bool
    {
        $this->updateWhere(
            ['id' => $sagaId, 'status' => PluginSaga::ST_RUNNING, 'claimed_pid' => $deadPid],
            ['claimed_pid' => $myPid, 'claimed_at' => date('Y-m-d H:i:s')]
        );
        $fresh = $this->find($sagaId);

        return $fresh !== null
            && (int) ($fresh['claimed_pid'] ?? -1) === $myPid
            && (string) ($fresh['status'] ?? '') === PluginSaga::ST_RUNNING;
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
