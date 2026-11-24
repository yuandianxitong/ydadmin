<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface PluginMigrationStore
{
    /** @return list<string> */
    public function successfulUpNames(int $pluginId): array;

    /** @return array<string, string> 迁移名 => 文件哈希 */
    public function upHashes(int $pluginId): array;

    public function markSuccess(int $pluginId, string $pluginCode, string $migrationName, string $fileHash, string $direction, ?string $pluginVersion, int $durationMs): void;

    public function markFailed(int $pluginId, string $pluginCode, string $migrationName, string $fileHash, string $direction, ?string $pluginVersion, int $durationMs, string $errorMsg): void;

    public function deleteSuccessfulUp(int $pluginId, string $migrationName): int;

    public function purgeForPlugin(int $pluginId): void;
}
