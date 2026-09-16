<?php

declare(strict_types=1);

namespace core\wechat\exception;

/**
 * 没能从微信拿到可用的结果：连接失败、超时、非 200、应答不是合法 JSON、应答缺关键字段、等 access_token 锁超时。
 */
final class WechatUnavailableException extends WechatException
{
}
