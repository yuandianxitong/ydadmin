<?php
declare(strict_types=1);

namespace core\wechat;

use core\exception\BusinessException;
use app\model\system\SystemConfig;
use EasyWeChat\OfficialAccount\Application as OfficialAccount;
use EasyWeChat\MiniApp\Application as MiniApp;

class WechatManager
{
    protected static ?OfficialAccount $officialAccount = null;
    protected static ?MiniApp $miniApp = null;

    /**
     * 获取公众号实例
     */
    public static function officialAccount(): OfficialAccount
    {
        if (self::$officialAccount !== null) {
            return self::$officialAccount;
        }

        $config = self::getOfficialAccountConfig();
        self::$officialAccount = new OfficialAccount($config);

        return self::$officialAccount;
    }

    /**
     * 获取小程序实例
     */
    public static function miniApp(): MiniApp
    {
        if (self::$miniApp !== null) {
            return self::$miniApp;
        }

        $config = self::getMiniAppConfig();
        self::$miniApp = new MiniApp($config);

        return self::$miniApp;
    }

    /**
     * 获取公众号配置
     */
    protected static function getOfficialAccountConfig(): array
    {
        $appId = (string)SystemConfig::getConfigValue('wechat_official_app_id', '');
        $appSecret = (string)SystemConfig::getConfigValue('wechat_official_app_secret', '');

        if (empty($appId) || empty($appSecret)) {
            throw new BusinessException(lang('business.wechat_official_config_incomplete'));
        }

        return [
            'app_id'  => $appId,
            'secret'  => $appSecret,
            'token'   => (string)SystemConfig::getConfigValue('wechat_official_token', ''),
            'aes_key' => (string)SystemConfig::getConfigValue('wechat_official_aes_key', ''),
        ];
    }

    /**
     * 获取小程序配置
     */
    protected static function getMiniAppConfig(): array
    {
        $appId = (string)SystemConfig::getConfigValue('wechat_mini_app_id', '');
        $appSecret = (string)SystemConfig::getConfigValue('wechat_mini_app_secret', '');

        if (empty($appId) || empty($appSecret)) {
            throw new BusinessException(lang('business.wechat_miniapp_config_incomplete'));
        }

        return [
            'app_id'  => $appId,
            'secret'  => $appSecret,
        ];
    }

    /**
     * 重置缓存实例
     */
    public static function reset(): void
    {
        self::$officialAccount = null;
        self::$miniApp = null;
    }
}
