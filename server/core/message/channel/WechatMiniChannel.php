<?php

declare(strict_types=1);

namespace core\message\channel;

use core\wechat\WechatAppConfig;

/** 小程序订阅消息：POST cgi-bin/message/subscribe/send，body {touser, template_id, page?, data}。 */
final class WechatMiniChannel extends AbstractWechatChannel
{
    protected function appConfig(): WechatAppConfig
    {
        return $this->configs->mini();
    }

    protected function api(): string
    {
        return 'cgi-bin/message/subscribe/send';
    }

    protected function linkField(): string
    {
        return 'page';
    }
}
