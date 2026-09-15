<?php

declare(strict_types=1);

namespace tests\fixtures\Queue;

/** 有 handle() 但没实现 QueueHandler：投递到它必须被拒绝，而不是鸭子类型地调用。 */
final class PlainConsumer
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): void
    {
    }
}
