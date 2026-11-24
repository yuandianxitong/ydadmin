<?php

declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\Plugin;
use core\base\Model;
use core\base\Repository;
use core\plugin\contracts\PluginStore;
use core\plugin\PluginStatus;
use support\Db;

final class PluginRepository extends Repository implements PluginStore
{
    protected bool $dataScoped = false;

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new Plugin();
    }

    public function findByCode(string $code): ?array
    {
        return $this->findWhere(['code' => $code]);
    }

    public function findReverseDependents(string $code): array
    {
        return $this->query()
            ->whereRaw("JSON_CONTAINS(depends, ?, '$')", [json_encode($code)])
            ->where('status', PluginStatus::ENABLED)
            ->pluck('code')
            ->all();
    }

    public function enabledRows(): array
    {
        return $this->query()
            ->where('status', PluginStatus::ENABLED)
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    public function enabledIcon(string $code): ?string
    {
        $icon = Db::table('plugins')
            ->where('code', $code)
            ->where('status', PluginStatus::ENABLED)
            ->whereNull('deleted_at')
            ->value('icon');

        return $icon === null ? null : (string) $icon;
    }
}
