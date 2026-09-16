<?php

declare(strict_types=1);

namespace core\wechat;

use core\contract\ConfigValueReader;
use core\wechat\exception\WechatNotConfiguredException;

/**
 * 三端微信应用配置解析（spec §3.4）。
 *
 * 每次调用现读 ConfigValueReader（它自带缓存且写配置路径会失效它），这里不再缓存一层：
 * 常驻内存下缓存了就看不到管理员刚改的配置（1.x 的 WechatManager 静态单例正是这个问题）。
 */
final class WechatConfigResolver
{
    /** @var array<string, array{string, string, string}> 端 → [appid 键, secret 键, 中文名] */
    private const SIDES = [
        'mini'     => ['wechat_mini_app_id', 'wechat_mini_app_secret', '小程序'],
        'official' => ['wechat_official_app_id', 'wechat_official_app_secret', '公众号'],
        'open'     => ['wechat_open_app_id', 'wechat_open_app_secret', '开放平台'],
    ];

    public function __construct(private readonly ConfigValueReader $config)
    {
    }

    /** @throws WechatNotConfiguredException */
    public function mini(): WechatAppConfig
    {
        return $this->resolve('mini');
    }

    /** @throws WechatNotConfiguredException */
    public function official(): WechatAppConfig
    {
        return $this->resolve('official');
    }

    /** @throws WechatNotConfiguredException */
    public function open(): WechatAppConfig
    {
        return $this->resolve('open');
    }

    private function resolve(string $side): WechatAppConfig
    {
        [$idKey, $secretKey, $label] = self::SIDES[$side];
        $appId = $this->read($idKey);
        $secret = $this->read($secretKey);

        $missing = [];
        if ($appId === '') {
            $missing[] = $idKey;
        }
        if ($secret === '') {
            $missing[] = $secretKey;
        }
        if ($missing !== []) {
            throw new WechatNotConfiguredException("微信{$label}配置不全，缺少：" . implode('、', $missing));
        }

        return new WechatAppConfig($appId, $secret);
    }

    private function read(string $key): string
    {
        $value = $this->config->getConfigValue($key);

        return is_string($value) ? trim($value) : '';
    }
}
