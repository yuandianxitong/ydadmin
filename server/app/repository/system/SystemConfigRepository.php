<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\SystemConfig;
use core\base\Model;
use core\base\Repository;
use core\contract\ConfigValueReader;
use Illuminate\Database\Eloquent\Builder;
use support\Cache;
use support\Redis;

/**
 * 系统配置仓储。有三份缓存：
 *   - system_config.all：全部启用配置（已按 config_type 转换），供后端读配置用（登录安全、密码长度等）；
 *   - system_config.public：启用且 is_public=1 的配置，供 config/global 用；
 *   - system_config.raw：全部启用配置的原始字符串，供需要分辨「明确关闭」与「值写坏了」的开关用。
 * 实际 key 带上 system_config.epoch。forgetCache() 只把代次加一，计算途中写回的旧值留在旧 key 上。
 */
class SystemConfigRepository extends Repository implements ConfigValueReader
{
    private const CACHE_KEY = 'system_config.all';

    private const PUBLIC_CACHE_KEY = 'system_config.public';

    private const RAW_CACHE_KEY = 'system_config.raw';

    private const CACHE_TTL = 3600;

    private const EPOCH_KEY = 'system_config.epoch';

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
        return $this->remember(self::CACHE_KEY, fn (): array => $this->convertRowsToKeyValue(
            $this->enabledQuery()->get()->toArray()
        ));
    }

    /**
     * 前端公开配置（config/global 的第一道闸）：启用且 is_public=1，config_key → 转换后的值。
     *
     * @return array<string, mixed>
     */
    public function getPublicConfigs(): array
    {
        return $this->remember(self::PUBLIC_CACHE_KEY, fn (): array => $this->convertRowsToKeyValue(
            $this->enabledQuery()->where($this->qualify('is_public'), 1)->get()->toArray()
        ));
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        $all = $this->getAllConfigs();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * 启用配置的原始字符串值（不按 config_type 转换）。布尔配置转换后 '0' 与写坏的 'banana' 都是 false，
     * 分不出「明确关掉」和「值坏了」；登录安全类开关要按原值判断（见 SystemConfigService::isSecuritySwitchOn()）。
     */
    public function getRawConfigValue(string $key, ?string $default = null): ?string
    {
        $raw = $this->remember(self::RAW_CACHE_KEY, function (): array {
            $result = [];
            foreach ($this->enabledQuery()->get()->toArray() as $row) {
                $result[(string) $row['config_key']] = (string) $row['config_value'];
            }

            return $result;
        });

        return array_key_exists($key, $raw) ? (string) $raw[$key] : $default;
    }

    /**
     * 某分组的启用配置原始行（契约 §2.7 index：config_value 保持库里的字符串），按 sort_order、id 升序。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRowsByGroup(string $group): array
    {
        return $this->enabledQuery()->where($this->qualify('config_group'), $group)->get()->toArray();
    }

    /**
     * 按键取原始行（不限 status；软删除的行视为不存在）。
     *
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        return $this->query()->where($this->qualify('config_key'), $key)->first()?->toArray();
    }

    public function updateValueByKey(string $key, string $value): int
    {
        return $this->query()->where($this->qualify('config_key'), $key)->update(['config_value' => $value]);
    }

    public function forgetCache(): void
    {
        Redis::incr(self::EPOCH_KEY);
    }

    /** @return Builder<Model> */
    private function enabledQuery(): Builder
    {
        $query = $this->query()->where($this->qualify('status'), 1);
        $query->orderBy($this->qualify('sort_order'))->orderBy($this->qualify('id'));

        return $query;
    }

    /**
     * @param \Closure(): array<string, mixed> $load
     * @return array<string, mixed>
     */
    private function remember(string $key, \Closure $load): array
    {
        $epoch = Redis::get(self::EPOCH_KEY);
        $versioned = $key . '.' . (is_numeric($epoch) ? (int) $epoch : 0);
        $cached = Cache::get($versioned);
        if (is_array($cached)) {
            return $cached;
        }
        $result = $load();
        Cache::set($versioned, $result, self::CACHE_TTL);

        return $result;
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
