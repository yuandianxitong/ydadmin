<?php

declare(strict_types=1);

namespace core\saga;

/**
 * Saga 运行期上下文：携带补偿所需的最小事实。`all()` 的返回值就是要落库的
 * `plugin_sagas.step_state`——崩溃后重建 SagaContext 靠的就是这份快照。
 */
final class SagaContext
{
    /** @param array<string, mixed> $state */
    public function __construct(private int $sagaId, private array $state = [])
    {
    }

    public function sagaId(): int
    {
        return $this->sagaId;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** 记账式追加（publishedHosts / ranMigrations 这类"本次真的做了什么"）。 */
    public function push(string $key, mixed $value): void
    {
        $current = $this->state[$key] ?? [];
        $current[] = $value;
        $this->state[$key] = $current;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->state;
    }
}
