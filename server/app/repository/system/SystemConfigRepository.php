<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\SystemConfig;
use core\base\Repository;
use think\facade\Cache;
use think\Model;

class SystemConfigRepository extends Repository
{
    protected function getModel(): Model
    {
        return new SystemConfig();
    }

    /**
     * 根据分组获取配置列表
     */
    public function getByGroup(string $group): array
    {
        return $this->model->where('config_group', $group)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 根据配置键查找
     */
    public function findByKey(string $key): ?array
    {
        $result = $this->model->where('config_key', $key)->find();
        return $result ? $result->toArray() : null;
    }

    /**
     * 根据配置键获取模型实例（用于更新操作）
     */
    public function findModelByKey(string $key): ?Model
    {
        return $this->model->where('config_key', $key)->find();
    }

    /**
     * 根据ID获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * 获取所有配置（键值对，带缓存）
     */
    public function getAllConfigs(): array
    {
        return Cache::remember('system_configs', function () {
            return SystemConfig::getAllConfigs();
        }, 3600);
    }

    /**
     * 获取配置值
     */
    public function getConfigValue(string $key, $default = null)
    {
        return SystemConfig::getConfigValue($key, $default);
    }

    /**
     * 设置配置值
     */
    public function setConfigValue(string $key, $value): bool
    {
        return SystemConfig::setConfigValue($key, $value);
    }

    /**
     * 根据ID更新配置值
     */
    public function updateConfigValueById(int $id, string $value): bool
    {
        return $this->model->where('id', $id)->update(['config_value' => $value]) !== false;
    }

    /**
     * 根据配置键更新配置值
     */
    public function updateConfigValueByKey(string $key, string $value): bool
    {
        return $this->model->where('config_key', $key)->update(['config_value' => $value]) !== false;
    }
}
