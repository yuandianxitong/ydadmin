<?php

declare(strict_types=1);

namespace core\message;

use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;

/**
 * 外发通道（短信 / 公众号模板消息 / 小程序订阅消息）。站内信不是通道：它由 MessageService 同步写库。
 *
 * 实现必须把一切失败归入两类异常之一，不得漏出其它异常，也不得把底层异常挂成 previous
 * （底层异常消息可能带手机号或带 access_token 的 URL）。
 */
interface ChannelInterface
{
    /** @throws MessageDefiniteFailure|MessageTransientFailure */
    public function send(ChannelMessage $message): void;
}
