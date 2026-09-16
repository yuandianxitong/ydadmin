<?php

declare(strict_types=1);

namespace core\sms;

use core\contract\ConfigValueReader;
use core\exception\BusinessException;
use core\sms\driver\AliyunSmsDriver;
use core\sms\driver\TencentSmsDriver;

/**
 * 短信驱动管理器：按系统配置 `sms_driver` 分派驱动（spec §3、§7.1），与 core\storage\StorageManager 同构。
 *
 * 🔴 不缓存驱动实例，也不缓存驱动名。本类经 php-di 以单例注入进 SmsCodeService，Webman 常驻内存下
 * 一旦缓存，管理员在系统配置里换了短信服务商也不会生效——验证码继续发给旧网关，接口却一路返回成功。
 * `driverName()` 每次现读（缓存归 ConfigValueReader 的实现管），`driver()` 每次现 new，用完即弃。
 * 本类不得新增任何 static 属性，也不得新增非 readonly 实例属性（check:context 规则一会拦前者）。
 *
 * 🔴 读配置异常不吞：`getConfigValue()` 的 $default 已经覆盖了「键不存在」这个正常场景，能抛上来的
 * 只剩数据库连不上这类基础设施故障——那种时候悄悄退回某个驱动没有任何意义。
 *
 * final（与 StorageManager、TokenManager 一致）：本类只负责选驱动，要假驱动的地方实现 SmsInterface，
 * 不继承本类——仓库里没有继承 Manager 做替身的先例。
 */
final class SmsManager
{
    public function __construct(private readonly ConfigValueReader $configReader)
    {
    }

    /** 按当前 `sms_driver` 配置取驱动实例（每次现读、现 new）。 */
    public function driver(): SmsInterface
    {
        return $this->driverFor($this->driverName());
    }

    /** 按显式驱动名取驱动实例（凭据取的仍是当前系统配置）。 */
    public function driverFor(string $driver): SmsInterface
    {
        return match ($driver) {
            'aliyun'  => new AliyunSmsDriver($this->credentials()),
            'tencent' => new TencentSmsDriver($this->credentials()),
            default   => throw new BusinessException(lang('business.sms_driver_unsupported', ['driver' => $driver])),
        };
    }

    /**
     * 当前配置的驱动名，默认 aliyun（种子值）。取值必须与配置项 `sms_driver` 的选项逐字一致
     * （aliyun|tencent），SmsManagerTest 的「驱动名闭环」那条在守这一点。
     */
    public function driverName(): string
    {
        return (string) $this->configReader->getConfigValue('sms_driver', 'aliyun');
    }

    /**
     * 两家共用一组键（spec §7.1）：access_key/access_secret 在腾讯云那边就是 SecretId/SecretKey，
     * sdk_app_id 只有腾讯云用。
     *
     * @return array{access_key: string, access_secret: string, sign_name: string, sdk_app_id: string}
     */
    private function credentials(): array
    {
        return [
            'access_key'    => (string) $this->configReader->getConfigValue('sms_access_key', ''),
            'access_secret' => (string) $this->configReader->getConfigValue('sms_access_secret', ''),
            'sign_name'     => (string) $this->configReader->getConfigValue('sms_sign_name', ''),
            'sdk_app_id'    => (string) $this->configReader->getConfigValue('sms_sdk_app_id', ''),
        ];
    }
}
