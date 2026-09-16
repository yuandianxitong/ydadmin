<?php

declare(strict_types=1);

namespace core\payment\exception;

/**
 * 凭据不全、私钥或公钥无法解析、未知渠道。消息只给日志（可含配置项名与私钥路径，不得含密钥内容），
 * 对外统一「支付方式暂不可用」。
 */
final class PaymentConfigException extends PaymentException
{
}
