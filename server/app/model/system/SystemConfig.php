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
    protected $name = 'system_configs';

    protected $fillable = [
        'config_key', 'config_value', 'config_group', 'config_type',
        'config_name', 'config_desc', 'config_options', 'config_depends',
        'sort_order', 'status'
    ];

    protected $type = [
        'sort_order' => 'integer',
        'status' => 'integer'
    ];

    /**
     * 根据类型转换配置值
     *
     * 保留在 Model 上是因为这是纯粹的值转换（无查询），Repository 和 Service 都会用到。
     */
    public static function convertValueByType(string $value, string $type)
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

    // ============================================================
    // core 层访问入口
    //
    // 以下静态方法**仅供 core/* 基础设施层使用**（如 WechatManager、PaymentManager、
    // StorageManager），它们不允许反向依赖 app/repository。
    //
    // app 层（Controller/Service）应统一通过 SystemConfigRepository 访问，
    // 不要直接调用这些静态方法。
    // ============================================================

    /**
     * 获取配置值（供 core 层使用，自动类型转换）
     */
    public static function getConfigValue(string $key, $default = null)
    {
        $config = static::where('config_key', $key)
            ->where('status', 1)
            ->find();

        if (!$config) {
            return $default;
        }

        return self::convertValueByType((string) $config->config_value, (string) $config->config_type);
    }

    /**
     * 根据分组获取配置键值对（供 core 层使用，带类型转换）
     */
    public static function getConfigsByGroup(string $group): array
    {
        $configs = static::where('config_group', $group)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = self::convertValueByType(
                (string) $config['config_value'],
                (string) $config['config_type']
            );
        }
        return $result;
    }
}
