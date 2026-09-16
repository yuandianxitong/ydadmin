<?php

declare(strict_types=1);

namespace app\service\payment;

/**
 * PaymentService::markPaid() 的结局（M5b spec §5.2）。只有 MISMATCH / NOT_FOUND 让回调应答失败、渠道重试；
 * ALREADY 是重复通知的幂等成功。
 */
final class MarkPaidOutcome
{
    public const PAID = 'paid';

    public const ALREADY = 'already';

    public const MISMATCH = 'mismatch';

    public const NOT_FOUND = 'not_found';
}
