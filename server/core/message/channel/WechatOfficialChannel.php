<?php

declare(strict_types=1);

namespace core\message\channel;

use core\wechat\WechatAppConfig;

/** 公众号模板消息：POST cgi-bin/message/template/send，body {touser, template_id, url?, data}。 */
final class WechatOfficialChannel extends AbstractWechatChannel
{
    protected function appConfig(): WechatAppConfig
    {
        return $this->configs->official();
    }

    protected function api(): string
    {
        return 'cgi-bin/message/template/send';
    }

    protected function linkField(): string
    {
        return 'url';
    }
}
