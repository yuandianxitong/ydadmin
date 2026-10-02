<?php

declare(strict_types=1);

namespace core\message\channel;

use core\mail\MailerInterface;
use core\message\ChannelInterface;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageFailure;
use support\Container;

/**
 * 邮件通道。主题在 ChannelMessage::templateId，正文在 link。
 * 配置缺失和地址非法是确定失败；连不上服务器由 SmtpMailer 抛暂时失败，交给队列重试。
 */
final class EmailChannel implements ChannelInterface
{
    public function send(ChannelMessage $message): void
    {
        try {
            Container::get(MailerInterface::class)->send($message->receiver, $message->templateId, $message->link);
        } catch (MessageFailure $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MessageDefiniteFailure('email send failed: ' . $e::class);
        }
    }
}
