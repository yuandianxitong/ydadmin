<?php

declare(strict_types=1);

namespace core\wechat\exception;

/**
 * 对应端的 appid 或 secret 未配置。消息只列缺失的配置键名，不含任何配置值。
 */
final class WechatNotConfiguredException extends WechatException
{
}
