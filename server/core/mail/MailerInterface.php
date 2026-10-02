<?php

declare(strict_types=1);

namespace core\mail;

use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;

/**
 * 按系统配置里的 SMTP 发一封纯文本邮件。每次 send() 现读配置。
 */
interface MailerInterface
{
    /**
     * @throws MessageDefiniteFailure  配置不全、地址非法、认证或收件被拒
     * @throws MessageTransientFailure 连不上服务器或暂时性拒收
     */
    public function send(string $to, string $subject, string $body): void;
}
