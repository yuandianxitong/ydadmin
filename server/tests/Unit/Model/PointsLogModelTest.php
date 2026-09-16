<?php

declare(strict_types=1);

namespace tests\Unit\Model;

use app\model\user\PointsLog;
use support\Context;
use tests\TestCase;

final class PointsLogModelTest extends TestCase
{
    public function test_type_constants(): void
    {
        $this->assertSame(1, PointsLog::TYPE_ADMIN_ADJUST);
        $this->assertSame(2, PointsLog::TYPE_REGISTER);
        $this->assertSame(3, PointsLog::TYPE_SIGN_IN);
        $this->assertSame(4, PointsLog::TYPE_CONSUME_AWARD);
        $this->assertSame(5, PointsLog::TYPE_CONSUME_DEDUCT);
    }

    public function test_type_text_follows_locale_and_matches_tp8_wording(): void
    {
        $log = new PointsLog();
        $log->forceFill(['type' => PointsLog::TYPE_SIGN_IN]);

        $this->assertSame('签到', $log->type_text);

        Context::set('locale', 'en');
        $this->assertSame('Daily check-in', $log->type_text);
    }

    public function test_type_text_is_empty_when_type_is_not_set(): void
    {
        $this->assertSame('', (new PointsLog())->type_text);
    }

    public function test_has_no_soft_deletes_and_no_updated_at(): void
    {
        $this->assertSame([], class_uses(PointsLog::class));
        $this->assertNull(PointsLog::UPDATED_AT);
    }

    public function test_integer_columns_are_cast(): void
    {
        $log = new PointsLog();
        $log->forceFill(['points' => '10', 'before_points' => '90', 'after_points' => '100']);

        $this->assertSame(10, $log->points);
        $this->assertSame(90, $log->before_points);
        $this->assertSame(100, $log->after_points);
    }
}
