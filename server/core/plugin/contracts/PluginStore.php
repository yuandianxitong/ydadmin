<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface PluginStore
{
    public function find(int $id): ?array;

    public function update(int $id, array $data): bool;

    public function updateWhere(array $where, array $data): int;

    /** @return list<array<string, mixed>> status = ENABLED 的行，顺序 id desc */
    public function enabledRows(): array;

    /** 已启用插件登记的图标；没有启用行或该行 icon 为 NULL 时返回 null */
    public function enabledIcon(string $code): ?string;
}
