<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

class SystemConfig extends Model
{
    protected $table = 'system_configs';

    protected $fillable = [
        'config_key', 'config_value', 'config_group', 'config_type',
        'config_name', 'config_desc', 'config_options', 'config_depends',
        'sort_order', 'status'
    ];

    protected $type = [
        'sort_order' => 'integer',
        'status' => 'integer'
    ];

    // 使用标准的时间戳字段（与基类一致）
    // protected $createTime = 'created_at';  // 基类默认值
    // protected $updateTime = 'updated_at';  // 基类默认值
    // protected $deleteTime = 'deleted_at';  // 基类默认值

    /**
     * 获取配置值并根据类型转换
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getConfigValue(string $key, $default = null)
    {
        $config = self::where('config_key', $key)
            ->where('status', 1)
            ->find();

        if (!$config) {
            return $default;
        }

        return self::convertValueByType($config->config_value, $config->config_type);
    }

    /**
     * 根据类型转换配置值
     * @param string $value
     * @param string $type
     * @return mixed
     */
    protected static function convertValueByType(string $value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return (bool)$value;
            case 'number':
                return is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : $value;
            case 'json':
                return json_decode($value, true) ?: [];
            default:
                return $value;
        }
    }

    /**
     * 获取分组配置
     * @param string $group
     * @return array
     */
    public static function getConfigsByGroup(string $group): array
    {
        $configs = self::where('config_group', $group)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = self::convertValueByType($config['config_value'], $config['config_type']);
        }

        return $result;
    }

    /**
     * 获取所有配置（返回键值对数组）
     * @return array
     */
    public static function getAllConfigs(): array
    {
        $configs = self::where('status', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = self::convertValueByType($config['config_value'], $config['config_type']);
        }

        return $result;
    }

    /**
     * 设置配置值
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function setConfigValue(string $key, $value): bool
    {
        $config = self::where('config_key', $key)->find();
        if (!$config) {
            return false;
        }

        // 根据类型处理值
        if ($config->config_type === 'json') {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $value = (string)$value;
        }

        $config->config_value = $value;


        return $config->save();
    }

    /**
     * 批量设置配置
     * @param array $configs
     * @return bool
     */
    public static function setConfigs(array $configs): bool
    {
        foreach ($configs as $key => $value) {
            self::setConfigValue($key, $value);
        }
        return true;
    }
}
