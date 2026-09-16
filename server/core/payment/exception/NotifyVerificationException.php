<?php

declare(strict_types=1);

namespace core\payment\exception;

/** 回调验签或核对（商户号、appid、时间窗）失败：应答失败让渠道重试，原因只写日志。 */
final class NotifyVerificationException extends PaymentException
{
}
