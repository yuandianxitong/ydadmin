<?php

declare(strict_types=1);

namespace core\payment\exception;

/** 明确失败：渠道返回业务错误、本地签名失败、请求确定未送达。调用方可以按失败处理（关单、冲正）。 */
final class GatewayException extends PaymentException
{
}
