<?php

declare(strict_types=1);

namespace app\service\operations;

use app\repository\operations\OutboxRepository;
use core\base\Service;
use core\context\RequestContext;
use core\queue\QueueDispatcher;
use core\queue\ReliableQueue;
use DI\Attribute\Inject;
use support\Context;
use support\Log;

final class OutboxService extends Service implements ReliableQueue
{
    #[Inject]
    protected OutboxRepository $repository;
    #[Inject]
    protected QueueDispatcher $dispatcher;

    public function enqueue(string $queue, array $data, ?string $dedupKey = null): int
    {
        $definition = config('queue.queues.' . $queue);
        if (!is_array($definition) || !is_a($definition['consumer'] ?? '', \core\queue\DeliveryHandler::class, true)) {
            throw new \InvalidArgumentException('未登记的可靠队列');
        }
        json_encode($data, JSON_THROW_ON_ERROR);

        return $this->runInTransaction(function () use ($queue, $data, $dedupKey): int {
            $id = $this->repository->insertDeduplicated([
                'queue' => $queue, 'payload' => $data,
                'dedup_key' => $dedupKey === null ? null : hash('sha256', $queue . ':' . $dedupKey),
                'trace_id' => RequestContext::traceId(), 'locale' => (string) Context::get('locale', 'zh_CN'),
                'status' => 0, 'attempts' => 0, 'available_at' => time(), 'created_at' => time(), 'updated_at' => time(),
            ]);
            if (config('queue.driver') === 'sync' && config('operations.inline_delivery', true)) {
                $this->afterCommit(function () use ($id): void {
                    try {
                        $this->publishOne($id);
                    } catch (\Throwable $e) {
                        Log::error('outbox.inline_failed', ['id' => $id, 'exception' => $e::class]);
                    }
                });
            }

            return $id;
        });
    }

    public function publish(int $limit = 100): int
    {
        $count = 0;
        foreach ($this->repository->due(max(1, min(1000, $limit))) as $id) {
            try {
                $count += (int) $this->publishOne($id);
            } catch (\Throwable $e) {
                Log::warning('outbox.publish_failed', ['id' => $id, 'exception' => $e::class]);
            }
        }

        return $count;
    }

    public function publishOne(int $id): bool
    {
        $token = bin2hex(random_bytes(16));
        $row = $this->repository->leaseForPublish($id, $token);
        if ($row === null) {
            return false;
        }
        $this->dispatcher->dispatch((string) $row['queue'], ['__delivery_id' => $id]);
        $this->repository->published($id, $token);

        return true;
    }

    public function claim(int $id, string $queue): ?array
    {
        return $this->runInTransaction(fn (): ?array => $this->repository->claim($id, $queue, bin2hex(random_bytes(16))));
    }

    public function complete(int $id, string $token): void
    {
        if (!$this->repository->ownedUpdate($id, $token, ['status' => 3, 'lease_until' => 0])) {
            $row = $this->repository->find($id);
            if ($row === null || (int) $row['status'] !== 3 || !hash_equals((string) $row['lease_token'], $token)) {
                throw new \core\queue\PermanentJobFailure('投递执行权已转移');
            }
        }
    }

    public function fail(int $id, string $token, \Throwable $error): bool
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            return false;
        }
        $terminal = $error instanceof \core\queue\PermanentJobFailure || (int) $row['attempts'] > (int) config('queue.queues.' . $row['queue'] . '.max_attempts', 0);
        $updated = $this->repository->ownedUpdate($id, $token, [
            'status' => $terminal ? 4 : 0, 'lease_until' => 0,
            'available_at' => time() + min(300, 5 * (2 ** min(6, (int) $row['attempts']))),
            'last_error' => $error::class,
        ]);

        return $updated && $terminal;
    }

    public function heartbeat(int $id, string $token): void
    {
        if (!$this->repository->ownedUpdate($id, $token, ['lease_until' => time() + (int) config('operations.delivery_lease_seconds', 3600)])) {
            throw new \RuntimeException('投递租约已失效');
        }
    }
}
