<?php

declare(strict_types=1);

namespace tests\Support\Payment;

use core\payment\exception\PaymentConfigException;
use core\payment\GatewayResolver;
use core\payment\PaymentGatewayInterface;

/** 假解析器：没登记的渠道当作「凭据不全」，抛 PaymentConfigException（与 PaymentManager 同一语义）。 */
final class FakeGatewayResolver implements GatewayResolver
{
    /**
     * @param array<string, PaymentGatewayInterface> $gateways channel => 网关
     * @param array<string, bool>                    $enabled  channel => 是否启用
     */
    public function __construct(
        private readonly array $gateways,
        private readonly array $enabled = ['wechat' => true, 'alipay' => true],
    ) {
    }

    public function isEnabled(string $channel): bool
    {
        return ($this->enabled[$channel] ?? false) === true;
    }

    public function gateway(string $channel): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$channel])) {
            throw new PaymentConfigException("FakeGatewayResolver 没有登记渠道 {$channel}");
        }

        return $this->gateways[$channel];
    }
}
