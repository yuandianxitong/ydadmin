<?php

declare(strict_types=1);

namespace tests\Unit\Model;

use app\model\user\BalanceLog;
use support\Context;
use tests\TestCase;

final class BalanceLogModelTest extends TestCase
{
    public function test_type_constants(): void
    {
        $this->assertSame(1, BalanceLog::TYPE_RECHARGE);
        $this->assertSame(2, BalanceLog::TYPE_CONSUME);
        $this->assertSame(3, BalanceLog::TYPE_REFUND);
        $this->assertSame(4, BalanceLog::TYPE_ADMIN_ADJUST);
    }

    public function test_type_text_follows_locale_and_matches_tp8_wording(): void
    {
        $log = new BalanceLog();
        $log->forceFill(['type' => BalanceLog::TYPE_ADMIN_ADJUST]);

        $this->assertSame('后台调整', $log->type_text);

        Context::set('locale', 'en');
        $this->assertSame('Admin adjustment', $log->type_text);
    }

    public function test_type_text_is_empty_when_type_is_not_set(): void
    {
        $this->assertSame('', (new BalanceLog())->type_text);
    }

    public function test_has_no_soft_deletes_and_no_updated_at(): void
    {
        $this->assertSame([], class_uses(BalanceLog::class));
        $this->assertNull(BalanceLog::UPDATED_AT);
    }

    public function test_decimal_columns_cast_to_two_decimal_strings(): void
    {
        $log = new BalanceLog();
        $log->forceFill(['amount' => '10', 'before_balance' => '5', 'after_balance' => '15']);

        $this->assertSame('10.00', $log->amount);
        $this->assertSame('5.00', $log->before_balance);
        $this->assertSame('15.00', $log->after_balance);
    }
}
