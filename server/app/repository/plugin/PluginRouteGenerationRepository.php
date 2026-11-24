<?php

declare(strict_types=1);

namespace app\repository\plugin;

use app\model\system\SystemConfig;
use core\base\Model;
use core\base\Repository;
use support\Db;

/** 插件路由版本号在 system_configs 里的那一行。只碰这一个 config_key。 */
final class PluginRouteGenerationRepository extends Repository
{
    protected bool $dataScoped = false;

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new SystemConfig();
    }

    /** 不加锁读取，没有这行返回 null。 */
    public function findValue(string $key): ?string
    {
        $row = Db::table('system_configs')
            ->where('config_key', $key)
            ->first();

        return $row === null ? null : (string) $row->config_value;
    }

    /**
     * 加行锁读取，调用方必须已在事务内。
     *
     * @return array{id: int, config_value: string}|null
     */
    public function lockByKey(string $key): ?array
    {
        $row = Db::table('system_configs')
            ->where('config_key', $key)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : ['id' => (int) $row->id, 'config_value' => (string) $row->config_value];
    }

    /** 老库没有这行种子时补一行，初始值 1；并发下已有同名行则忽略。 */
    public function seed(string $key): void
    {
        $now = date('Y-m-d H:i:s');
        Db::table('system_configs')->insertOrIgnore([
            'config_key' => $key,
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
    }

    public function writeValue(int $id, string $value): void
    {
        Db::table('system_configs')->where('id', $id)->update([
            'config_value' => $value,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
