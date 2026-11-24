<?php

declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\PluginMigration;
use core\base\Model;
use core\base\Repository;
use core\plugin\contracts\PluginMigrationStore;

final class PluginMigrationRepository extends Repository implements PluginMigrationStore
{
    protected bool $dataScoped = false;

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new PluginMigration();
    }

    public function successfulUpNames(int $pluginId): array
    {
        return $this->query()
            ->where('plugin_id', $pluginId)
            ->where('direction', PluginMigration::DIRECTION_UP)
            ->where('status', PluginMigration::STATUS_SUCCESS)
            ->pluck('migration_name')
            ->all();
    }

    public function upHashes(int $pluginId): array
    {
        $rows = $this->query()
            ->where('plugin_id', $pluginId)
            ->where('direction', PluginMigration::DIRECTION_UP)
            ->where('status', PluginMigration::STATUS_SUCCESS)
            ->get(['migration_name', 'file_hash'])
            ->toArray();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['migration_name']] = (string) $row['file_hash'];
        }

        return $out;
    }

    public function markSuccess(int $pluginId, string $pluginCode, string $migrationName, string $fileHash, string $direction, ?string $pluginVersion, int $durationMs): void
    {
        $this->query()->where('plugin_id', $pluginId)->where('migration_name', $migrationName)
            ->where('status', PluginMigration::STATUS_SUCCESS)->delete();
        $this->query()->where('plugin_id', $pluginId)->where('migration_name', $migrationName)
            ->where('direction', $direction)->where('status', PluginMigration::STATUS_FAILED)->delete();
        $this->query()->insert([
            'plugin_id' => $pluginId,
            'plugin_code' => $pluginCode,
            'migration_name' => $migrationName,
            'file_hash' => $fileHash,
            'direction' => $direction,
            'plugin_version' => $pluginVersion,
            'duration_ms' => $durationMs,
            'status' => PluginMigration::STATUS_SUCCESS,
            'error_msg' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(int $pluginId, string $pluginCode, string $migrationName, string $fileHash, string $direction, ?string $pluginVersion, int $durationMs, string $errorMsg): void
    {
        $this->query()->where('plugin_id', $pluginId)->where('migration_name', $migrationName)
            ->where('direction', $direction)->where('status', PluginMigration::STATUS_FAILED)->delete();
        $this->query()->insert([
            'plugin_id' => $pluginId,
            'plugin_code' => $pluginCode,
            'migration_name' => $migrationName,
            'file_hash' => $fileHash,
            'direction' => $direction,
            'plugin_version' => $pluginVersion,
            'duration_ms' => $durationMs,
            'status' => PluginMigration::STATUS_FAILED,
            'error_msg' => substr($errorMsg, 0, 1024),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteSuccessfulUp(int $pluginId, string $migrationName): int
    {
        return $this->query()->where('plugin_id', $pluginId)->where('migration_name', $migrationName)
            ->where('direction', PluginMigration::DIRECTION_UP)
            ->where('status', PluginMigration::STATUS_SUCCESS)->delete();
    }

    public function purgeForPlugin(int $pluginId): void
    {
        $this->query()->where('plugin_id', $pluginId)->delete();
    }
}
