<?php

declare(strict_types=1);

namespace core\queue;

interface ReliableQueue
{
    /** @param array<string, mixed> $data */
    public function enqueue(string $queue, array $data, ?string $dedupKey = null): int;

    /** @return array<string, mixed>|null */
    public function claim(int $id, string $queue): ?array;

    public function complete(int $id, string $token): void;

    public function fail(int $id, string $token, \Throwable $error): bool;

    public function heartbeat(int $id, string $token): void;
}
