<?php
declare(strict_types=1);

namespace core\storage;

use core\storage\driver\LocalDriver;
use core\storage\driver\AliyunOssDriver;
use core\storage\driver\TencentCosDriver;
use core\storage\driver\QiniuDriver;
use core\exception\BusinessException;
use app\model\system\SystemConfig;

class StorageManager
{
    protected static ?StorageInterface $instance = null;
    protected static ?string $currentDriver = null;

    /**
     * 获取存储驱动实例
     * @param string|null $driver 指定驱动，null 则读取系统配置
     * @return StorageInterface
     */
    public static function disk(?string $driver = null): StorageInterface
    {
        $driver = $driver ?? self::getConfigDriver();

        // 如果已缓存且驱动未变，直接返回
        if (self::$instance !== null && self::$currentDriver === $driver) {
            return self::$instance;
        }

        self::$instance = self::createDriver($driver);
        self::$currentDriver = $driver;

        return self::$instance;
    }

    /**
     * 从系统配置读取当前存储驱动
     */
    protected static function getConfigDriver(): string
    {
        try {
            return (string)(SystemConfig::getConfigValue('storage_driver', 'local'));
        } catch (\Exception $e) {
            return 'local';
        }
    }

    /**
     * 创建驱动实例
     */
    protected static function createDriver(string $driver): StorageInterface
    {
        return match ($driver) {
            'local'   => new LocalDriver(),
            'aliyun'  => new AliyunOssDriver(self::getAliyunConfig()),
            'tencent' => new TencentCosDriver(self::getTencentConfig()),
            'qiniu'   => new QiniuDriver(self::getQiniuConfig()),
            default   => throw new BusinessException("不支持的存储驱动: {$driver}"),
        };
    }

    /**
     * 获取阿里云 OSS 配置
     */
    protected static function getAliyunConfig(): array
    {
        return [
            'access_key'    => (string)SystemConfig::getConfigValue('storage_oss_access_key', ''),
            'access_secret' => (string)SystemConfig::getConfigValue('storage_oss_access_secret', ''),
            'bucket'        => (string)SystemConfig::getConfigValue('storage_oss_bucket', ''),
            'endpoint'      => (string)SystemConfig::getConfigValue('storage_oss_endpoint', ''),
            'domain'        => (string)SystemConfig::getConfigValue('storage_oss_domain', ''),
        ];
    }

    /**
     * 获取腾讯云 COS 配置
     */
    protected static function getTencentConfig(): array
    {
        return [
            'secret_id'  => (string)SystemConfig::getConfigValue('storage_cos_secret_id', ''),
            'secret_key' => (string)SystemConfig::getConfigValue('storage_cos_secret_key', ''),
            'bucket'     => (string)SystemConfig::getConfigValue('storage_cos_bucket', ''),
            'region'     => (string)SystemConfig::getConfigValue('storage_cos_region', ''),
            'domain'     => (string)SystemConfig::getConfigValue('storage_cos_domain', ''),
        ];
    }

    /**
     * 获取七牛云配置
     */
    protected static function getQiniuConfig(): array
    {
        return [
            'access_key'  => (string)SystemConfig::getConfigValue('storage_qiniu_access_key', ''),
            'secret_key'  => (string)SystemConfig::getConfigValue('storage_qiniu_secret_key', ''),
            'bucket'      => (string)SystemConfig::getConfigValue('storage_qiniu_bucket', ''),
            'domain'      => (string)SystemConfig::getConfigValue('storage_qiniu_domain', ''),
        ];
    }

    /**
     * 重置缓存实例（配置变更后调用）
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$currentDriver = null;
    }
}
