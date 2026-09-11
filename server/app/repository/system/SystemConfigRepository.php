<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\SystemConfig;
use core\base\Model;
use core\base\Repository;
use support\Cache;

/** 系统配置仓储。全部启用配置缓存在 system_config.all，写配置的路径必须经 forgetCache()。 */
class SystemConfigRepository extends Repository
{
    private const CACHE_KEY = 'system_config.all';

    private const CACHE_TTL = 3600;

    /** @var list<string> */
    protected array $sortable = ['id', 'sort_order'];

    protected function getModel(): Model
    {
        return new SystemConfig();
    }

    /**
     * 全部启用配置：config_key → 按 config_type 转换后的值。
     *
     * @return array<string, mixed>
     */
    public function getAllConfigs(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $result = $this->convertRowsToKeyValue(
            $this->query()->where($this->qualify('status'), 1)->orderBy($this->qualify('sort_order'))->get()->toArray()
        );
        Cache::set(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        $all = $this->getAllConfigs();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * 按分组取启用配置（无缓存）。
     *
     * @return array<string, mixed>
     */
    public function getConfigsByGroup(string $group): array
    {
        return $this->convertRowsToKeyValue(
            $this->query()->where($this->qualify('config_group'), $group)->where($this->qualify('status'), 1)
                ->orderBy($this->qualify('sort_order'))->get()->toArray()
        );
    }

    public function forgetCache(): void
    {
        Cache::delete(self::CACHE_KEY);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function convertRowsToKeyValue(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['config_key']] = SystemConfig::convertValueByType((string) $row['config_value'], (string) $row['config_type']);
        }

        return $result;
    }
}
