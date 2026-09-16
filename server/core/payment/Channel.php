<?php

declare(strict_types=1);

namespace core\payment;

/** 支付渠道标识（M5b spec §4）。 */
final class Channel
{
    public const WECHAT = 'wechat';

    public const ALIPAY = 'alipay';

    public const ALL = [self::WECHAT, self::ALIPAY];
}
