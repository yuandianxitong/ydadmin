<?php

declare(strict_types=1);

namespace core\payment\dto;

/**
 * 网关下单请求。notifyUrl 由 PaymentService 解析好传入（驱动不读 site_url、不看请求头）；
 * openid 仅 jsapi 需要，clientIp 仅微信 h5 需要。
 */
final readonly class CreateOrderRequest
{
    public function __construct(
        public string $orderNo,
        public string $tradeType,
        public string $subject,
        public int $amountCents,
        public \DateTimeImmutable $expiresAt,
        public string $notifyUrl,
        public ?string $openid = null,
        public ?string $clientIp = null,
    ) {
    }
}
