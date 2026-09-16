<?php

declare(strict_types=1);

namespace core\payment\exception;

/**
 * 结果不确定：连接或读超时、5xx、应答无法解析、应答验签失败。请求可能已在渠道侧生效，
 * 调用方不得按失败回滚（订单保持 pending、退款保持 processing），交给补查或对账终结。
 */
final class GatewayResultUnknownException extends PaymentException
{
}
