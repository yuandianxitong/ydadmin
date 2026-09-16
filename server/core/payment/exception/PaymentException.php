<?php

declare(strict_types=1);

namespace core\payment\exception;

/**
 * 支付异常基类。故意不继承 core\exception\BusinessException：全局异常处理器会把业务异常的消息原样
 * 返回客户端，而这里的消息是渠道错误码、配置缺项等内部细节，只进日志；Service 负责转成对外文案。
 */
class PaymentException extends \RuntimeException
{
}
