<?php

declare(strict_types=1);

namespace core\wechat;

/** 一端（小程序 / 公众号 / 开放平台）的 appid 与 secret。每次调用现读现建，不缓存。 */
final readonly class WechatAppConfig
{
    public function __construct(
        public string $appId,
        public string $secret,
    ) {
    }
}
