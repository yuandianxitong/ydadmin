<?php

declare(strict_types=1);

namespace core\payment\config;

/**
 * 支付宝驱动配置（M5b spec §6），由 PaymentManager 从 payment 组配置组装。
 * privateKey / alipayPublicKey 是 PEM 正文（可省略头尾行，驱动负责补齐）；只支持公钥模式。
 */
final readonly class AlipayConfig
{
    public const GATEWAY = 'https://openapi.alipay.com/gateway.do';

    public const SANDBOX_GATEWAY = 'https://openapi-sandbox.dl.alipaydev.com/gateway.do';

    public function __construct(
        public string $appId,
        public string $privateKey,
        public string $alipayPublicKey,
        public bool $sandbox,
        public float $connectTimeout,
        public float $timeout,
    ) {
    }

    public function gatewayUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_GATEWAY : self::GATEWAY;
    }
}
