<?php

declare(strict_types=1);

namespace core\payment;

use core\payment\exception\PaymentConfigException;

/**
 * 按渠道取网关（M5b spec §5.8）。enabled 只拦新下单：查询、关单、退款、对账、回调验签只要求凭据齐全，
 * 否则管理员关掉渠道后在途订单与退款无人处理。
 */
interface GatewayResolver
{
    public function isEnabled(string $channel): bool;

    /**
     * 不看 enabled，只要求凭据齐全。
     *
     * @throws PaymentConfigException 凭据不全、密钥无法解析或未知渠道
     */
    public function gateway(string $channel): PaymentGatewayInterface;
}
