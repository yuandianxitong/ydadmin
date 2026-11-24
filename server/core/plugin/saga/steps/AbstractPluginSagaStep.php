<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\PluginPaths;
use core\saga\CompensatableStep;

/**
 * 插件 saga step 的共同底座：构造参数 $saga 是 plugin_sagas 整行，本类提供从中读取 id、code、目录和 payload 的访问器。
 *
 * 每个 step 的 name() 返回的字面量会持久化进 plugin_sagas.completed_steps，崩溃恢复按它回查 step，不得改动。
 */
abstract class AbstractPluginSagaStep implements CompensatableStep
{
    /** @param array<string, mixed> $saga `plugin_sagas` 整行 */
    public function __construct(protected readonly array $saga)
    {
    }

    protected function sagaId(): int
    {
        return (int) $this->saga['id'];
    }

    protected function pluginId(): int
    {
        return (int) $this->saga['plugin_id'];
    }

    protected function code(): string
    {
        return (string) $this->saga['code'];
    }

    protected function pluginRoot(): string
    {
        return rtrim(base_path(), '/');
    }

    protected function pluginDir(): string
    {
        return PluginPaths::dir($this->code());
    }

    /**
     * saga 登记时由发起方写下的入参快照。
     *
     * saga 在另一个进程、另一个时刻执行，发起时的事实（来源状态、是否解压、host SDK 版本）
     * 已无法复原，step 必须读这里而不是运行时再判一次。
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $raw = $this->saga['payload'] ?? '';
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
