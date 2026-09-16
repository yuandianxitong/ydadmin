<?php

declare(strict_types=1);

namespace core\wechat\exception;

/**
 * 微信基础层异常基类。
 *
 * 不继承 core\exception\BusinessException：全局异常处理器会把 BusinessException 的消息当业务文案回给客户端，
 * 而这里的消息是给日志看的内部细节（接口名、errcode、缺失的配置键）。服务层负责把它们翻译成 lang() 文案。
 */
class WechatException extends \RuntimeException
{
}
