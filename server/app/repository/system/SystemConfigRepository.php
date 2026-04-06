<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\SystemConfig;
use core\base\Repository;
use core\cache\CacheableRepository;
use think\Model;

class SystemConfigRepository extends Repository
{
    use CacheableRepository;

    protected string $cacheTag = 'config';
    protected int $cacheTTL = 3600;

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
        return $this->cacheRemember('system_configs', function () {
            $configs = $this->model->where('status', 1)
                ->order('sort_order', 'asc')
                ->select()
                ->toArray();

            $result = [];
            foreach ($configs as $config) {
                $result[$config['config_key']] = SystemConfig::convertValueByType(
                    (string) $config['config_value'],
                    (string) $config['config_type']
                );
            }
            return $result;
        });
    }

    /**
     * 根据分组获取配置键值对（带类型转换）
     */
    public function getConfigsByGroup(string $group): array
    {
        $configs = $this->model->where('config_group', $group)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = SystemConfig::convertValueByType(
                (string) $config['config_value'],
                (string) $config['config_type']
            );
        }
        return $result;
    }

    /**
     * 获取配置值（带类型转换）
     */
    public function getConfigValue(string $key, $default = null)
    {
        $config = $this->model->where('config_key', $key)
            ->where('status', 1)
            ->find();

        if (!$config) {
            return $default;
        }

        return SystemConfig::convertValueByType(
            (string) $config->config_value,
            (string) $config->config_type
        );
    }

    /**
     * 设置配置值
     */
    public function setConfigValue(string $key, $value): bool
    {
        $config = $this->model->where('config_key', $key)->find();
        if (!$config) {
            return false;
        }

        if ($config->config_type === 'json') {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $value = (string) $value;
        }

        return $this->model->where('config_key', $key)->update(['config_value' => $value]) !== false;
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

    /**
     * 配置项总数
     */
    public function getTotalCount(): int
    {
        return $this->model->where('status', 1)->count();
    }
}
