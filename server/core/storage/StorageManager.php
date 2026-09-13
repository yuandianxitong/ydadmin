<?php

declare(strict_types=1);

namespace core\storage;

use core\contract\ConfigValueReader;
use core\exception\BusinessException;
use core\storage\driver\AliyunOssDriver;
use core\storage\driver\LocalDriver;
use core\storage\driver\QiniuDriver;
use core\storage\driver\TencentCosDriver;

/**
 * 存储驱动管理器：按系统配置 `storage_driver` 分派驱动（spec §6.5、契约 §2.9.3）。
 *
 * 🔴 不缓存驱动实例，也不缓存驱动名。本类经 php-di 以单例注入进 UploadController /
 * FileService，Webman 常驻内存下一旦缓存，管理员在系统配置页切了存储方式也不会生效——
 * 文件继续落在旧驱动上，接口却一路返回成功，属于最难排查的静默失败。TP8 用
 * `static $instance` + 配置变更时 `reset()` 打补丁，本项目直接不缓存：`getConfigDriver()`
 * 每次现读（缓存归 `ConfigValueReader` 的实现管，见该接口的注释），
 * `disk()` 每次现 new，用完即弃。本类不得新增任何 static 属性，也不得新增非 readonly
 * 实例属性（`composer check:context` 规则一用反射扫 app/、core/，会拦前者）。
 *
 * 🔴 读配置异常不吞。`getConfigValue()` 的 `$default` 已经覆盖了「键不存在」这个正常场景，
 * 能抛上来的只剩数据库连不上这类基础设施故障；那种时候悄悄退回 local，等于在配置系统抖动
 * 的窗口期把本该上云的文件写进本地磁盘，且没有任何人能发现。让异常照常向上冒是更安全的失败方式。
 */
final class StorageManager
{
    public function __construct(private readonly ConfigValueReader $configReader)
    {
    }

    /** 按当前 `storage_driver` 配置取驱动实例（每次现读、现 new）。 */
    public function disk(): StorageInterface
    {
        return $this->diskFor($this->driverName());
    }

    /**
     * 按显式驱动名取驱动实例。Task 6 删 `files` 行时用它按 `files.storage` 回删旧驱动上的
     * 物理文件（凭据取的仍是当前系统配置——TP8 也是这么做的，换过凭据的历史文件删不掉时
     * 由调用方记 warning，不阻断删库记录）。
     */
    public function diskFor(string $driver): StorageInterface
    {
        return match ($driver) {
            'local'   => new LocalDriver(),
            'aliyun'  => new AliyunOssDriver($this->getAliyunConfig()),
            'tencent' => new TencentCosDriver($this->getTencentConfig()),
            'qiniu'   => new QiniuDriver($this->getQiniuConfig()),
            default   => throw new BusinessException(lang('business.storage_driver_unsupported', ['driver' => $driver])),
        };
    }

    /**
     * 当前配置的驱动名，默认 `local`。上传响应的 `storage` 字段与 `files.storage` 列都用它，
     * 所以取值必须与配置项 `storage_driver` 的选项逐字一致（local|aliyun|tencent|qiniu）。
     */
    public function driverName(): string
    {
        return (string) $this->configReader->getConfigValue('storage_driver', 'local');
    }

    /**
     * 阿里云 OSS 凭据（键名对齐契约 §2.9.3 表格，`region` 是本项目显式新增的一项：
     * v2 SDK 的 V4 签名必须有 region，留空时由驱动从 endpoint 推导）。
     *
     * @return array{access_key: string, access_secret: string, bucket: string, endpoint: string, region: string, domain: string}
     */
    private function getAliyunConfig(): array
    {
        return [
            'access_key'    => (string) $this->configReader->getConfigValue('storage_oss_access_key', ''),
            'access_secret' => (string) $this->configReader->getConfigValue('storage_oss_access_secret', ''),
            'bucket'        => (string) $this->configReader->getConfigValue('storage_oss_bucket', ''),
            'endpoint'      => (string) $this->configReader->getConfigValue('storage_oss_endpoint', ''),
            'region'        => (string) $this->configReader->getConfigValue('storage_oss_region', ''),
            'domain'        => (string) $this->configReader->getConfigValue('storage_oss_domain', ''),
        ];
    }

    /**
     * 腾讯云 COS 凭据。
     *
     * @return array{secret_id: string, secret_key: string, bucket: string, region: string, domain: string}
     */
    private function getTencentConfig(): array
    {
        return [
            'secret_id'  => (string) $this->configReader->getConfigValue('storage_cos_secret_id', ''),
            'secret_key' => (string) $this->configReader->getConfigValue('storage_cos_secret_key', ''),
            'bucket'     => (string) $this->configReader->getConfigValue('storage_cos_bucket', ''),
            'region'     => (string) $this->configReader->getConfigValue('storage_cos_region', ''),
            'domain'     => (string) $this->configReader->getConfigValue('storage_cos_domain', ''),
        ];
    }

    /**
     * 七牛云凭据。
     *
     * @return array{access_key: string, secret_key: string, bucket: string, domain: string}
     */
    private function getQiniuConfig(): array
    {
        return [
            'access_key' => (string) $this->configReader->getConfigValue('storage_qiniu_access_key', ''),
            'secret_key' => (string) $this->configReader->getConfigValue('storage_qiniu_secret_key', ''),
            'bucket'     => (string) $this->configReader->getConfigValue('storage_qiniu_bucket', ''),
            'domain'     => (string) $this->configReader->getConfigValue('storage_qiniu_domain', ''),
        ];
    }
}
