<?php

declare(strict_types=1);

namespace core\message\exception;

/**
 * 消息发送失败的基类（M6b spec §4.4、§4.6）。消费者只按两个子类分流：
 *   - MessageDefiniteFailure：重试也不会成功（配置缺失、模板或变量错误、微信拒收），写失败后不再重试；
 *   - MessageTransientFailure：网络或微信繁忙，交给队列重试。
 *
 * 消息只含 errcode、固定英文短语、变量 key 与异常类名——它会原样进 message_logs.error_msg，
 * 不得带手机号、openid、access_token、appsecret 或变量值。
 */
class MessageFailure extends \RuntimeException
{
}
