<?php

declare(strict_types=1);

namespace core\plugin;

/**
 * 运行期插件清单缓存，由 PluginManager::bootAll() 在 worker 启动时写入，请求期只读。
 *
 * register()/clear() 只能在 boot 阶段或测试里调用，不得出现在请求路径上。
 */
class PluginRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $plugins = [];

    /**
     * @param array<string, mixed> $meta
     */
    public function register(string $code, array $meta): void
    {
        $this->plugins[$code] = $meta;
    }

    public function has(string $code): bool
    {
        return isset($this->plugins[$code]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $code): ?array
    {
        return $this->plugins[$code] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    public function clear(): void
    {
        $this->plugins = [];
    }
}
