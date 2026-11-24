<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use core\plugin\contracts\RouteGenerationStore;
use core\plugin\PluginStatus;
use support\Db;

class PluginRouteGeneration implements RouteGenerationStore
{
    public const KEY = 'plugin_route_generation';

    public function read(): ?int
    {
        try {
            $row = Db::table('system_configs')
                ->where('config_key', self::KEY)
                ->first();
        } catch (\Throwable) {
            return null;
        }
        if ($row === null || !is_numeric($row->config_value)) {
            return null;
        }

        return (int) $row->config_value;
    }

    /**
     * 这次状态写入是否要把版本号加一。
     *
     * 加一当且仅当：写入的是安装最终态（ENABLED / DISABLED / FAILED，且来源是安装，
     * 不是 INSTALLING 占位），或者写入使「是不是 ENABLED」发生变化。
     */
    public function shouldBump(int $before, int $after, bool $installFinal): bool
    {
        if ($installFinal && in_array($after, [PluginStatus::ENABLED, PluginStatus::DISABLED, PluginStatus::FAILED], true)) {
            return true;
        }

        return ($before === PluginStatus::ENABLED) !== ($after === PluginStatus::ENABLED);
    }

    /**
     * 版本号加一。调用方必须已在 `Db::connection()->transaction()`（或
     * `Service::runInTransaction()`）内，`lockForUpdate()` 才有意义。
     */
    public function bump(): void
    {
        $row = $this->lockedRow();
        if ($row === null) {
            // 老库没有这行种子：同事务内补一行（初始 1），再按下面的逻辑加一。
            $now = date('Y-m-d H:i:s');
            Db::table('system_configs')->insertOrIgnore([
                'config_key' => self::KEY,
                'config_value' => '1',
                'config_group' => 'basic',
                'config_type' => 'string',
                'config_name' => '插件路由版本',
                'config_desc' => '插件路由匹配表版本，状态变更时加一',
                'sort_order' => 0,
                'status' => 1,
                'is_public' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $row = $this->lockedRow();
        }
        if ($row === null) {
            throw new \RuntimeException('plugin_route_generation 行无法创建');
        }
        Db::table('system_configs')->where('id', $row->id)->update([
            'config_value' => (string) (((int) $row->config_value) + 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function lockedRow(): ?object
    {
        return Db::table('system_configs')
            ->where('config_key', self::KEY)
            ->lockForUpdate()
            ->first();
    }
}
