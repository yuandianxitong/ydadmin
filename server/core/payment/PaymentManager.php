<?php
declare(strict_types=1);

namespace core\payment;

use core\payment\driver\AlipayDriver;
use core\payment\driver\WechatPayDriver;
use core\exception\BusinessException;
use app\model\system\SystemConfig;

class PaymentManager
{
    protected static array $instances = [];

    /**
     * 获取支付驱动实例
     * @param string $channel 支付渠道：alipay / wechat
     * @return PaymentInterface
     */
    public static function channel(string $channel): PaymentInterface
    {
        if (isset(self::$instances[$channel])) {
            return self::$instances[$channel];
        }

        self::$instances[$channel] = self::createDriver($channel);
        return self::$instances[$channel];
    }

    protected static function createDriver(string $channel): PaymentInterface
    {
        return match ($channel) {
            'alipay' => new AlipayDriver(self::getAlipayConfig()),
            'wechat' => new WechatPayDriver(self::getWechatConfig()),
            default  => throw new BusinessException("不支持的支付渠道: {$channel}"),
        };
    }

    protected static function getAlipayConfig(): array
    {
        $enabled = SystemConfig::getConfigValue('pay_alipay_enabled', false);
        if (!$enabled) {
            throw new BusinessException(lang('business.alipay_not_enabled'));
        }

        return [
            'app_id'      => (string)SystemConfig::getConfigValue('pay_alipay_app_id', ''),
            'private_key' => (string)SystemConfig::getConfigValue('pay_alipay_private_key', ''),
            'public_key'  => (string)SystemConfig::getConfigValue('pay_alipay_public_key', ''),
            'notify_url'  => (string)SystemConfig::getConfigValue('pay_alipay_notify_url', ''),
        ];
    }

    protected static function getWechatConfig(): array
    {
        $enabled = SystemConfig::getConfigValue('pay_wechat_enabled', false);
        if (!$enabled) {
            throw new BusinessException(lang('business.wechat_pay_not_enabled'));
        }

        return [
            'app_id'           => (string)SystemConfig::getConfigValue('pay_wechat_app_id', ''),
            'mch_id'           => (string)SystemConfig::getConfigValue('pay_wechat_mch_id', ''),
            'api_key'          => (string)SystemConfig::getConfigValue('pay_wechat_api_key', ''),
            'api_v3_key'       => (string)SystemConfig::getConfigValue('pay_wechat_api_v3_key', ''),
            'serial_no'        => (string)SystemConfig::getConfigValue('pay_wechat_serial_no', ''),
            'private_key_path' => (string)SystemConfig::getConfigValue('pay_wechat_private_key_path', ''),
            'cert_path'        => (string)SystemConfig::getConfigValue('pay_wechat_cert_path', ''),
            'notify_url'       => (string)SystemConfig::getConfigValue('pay_wechat_notify_url', ''),
        ];
    }

    /**
     * 重置缓存实例（配置变更后调用）
     */
    public static function reset(): void
    {
        self::$instances = [];
    }
}
