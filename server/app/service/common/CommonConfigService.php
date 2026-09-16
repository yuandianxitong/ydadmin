<?php

declare(strict_types=1);

namespace app\service\common;

use core\base\Service;
use core\contract\ConfigValueReader;
use DI\Attribute\Inject;

/**
 * C 端公开配置（M6a spec §4.9）。固定白名单，不按 system_configs.is_public 动态放出：
 * 那个标记日后可能被误改，这个接口公开、不需要登录，漏一个 secret 就是事故。
 *
 * 字段来源：1.x 返回 basic 组的 site_name / site_logo / site_description / site_status / site_close_tip，
 * uniapp app.store 另读 site_url 拼静态资源域名；pc 登录页读 wechat_open_app_id，缺键时显示「未配置」。
 *
 * 容器单例，无实例态。
 */
class CommonConfigService extends Service
{
    private const ALWAYS = ['site_name', 'site_url', 'site_logo', 'site_description', 'site_status', 'site_close_tip'];

    #[Inject]
    protected ConfigValueReader $config;

    /** @return array<string, mixed> */
    public function publicConfig(): array
    {
        $result = [];
        foreach (self::ALWAYS as $key) {
            $result[$key] = $this->config->getConfigValue($key, '');
        }

        $openAppId = trim((string) $this->config->getConfigValue('wechat_open_app_id', ''));
        if ($openAppId !== '') {
            $result['wechat_open_app_id'] = $openAppId;
        }

        return $result;
    }
}
