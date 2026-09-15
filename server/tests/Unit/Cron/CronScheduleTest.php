<?php

declare(strict_types=1);

namespace tests\Unit\Cron;

use core\cron\CronSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\TestCase;

final class CronScheduleTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool}> */
    public static function expressions(): array
    {
        return [
            '每分钟'            => ['* * * * *', true],
            '每 5 分钟'         => ['*/5 * * * *', true],
            '每天 3 点'         => ['0 3 * * *', true],
            '每周一三五 9 点'   => ['0 9 * * 1,3,5', true],
            '每月 1、15 日 3 点' => ['0 3 1,15 * *', true],
            '前后空白'          => ['  0 3 * * *  ', true],
            '宏被拒绝（库本身接受）' => ['@hourly', false],
            '6 段被拒绝'        => ['0 0 * * * *', false],
            '4 段被拒绝'        => ['* * * *', false],
            '分钟越界'          => ['61 * * * *', false],
            '空串'              => ['', false],
        ];
    }

    #[DataProvider('expressions')]
    public function test_is_valid_accepts_exactly_five_parsable_fields(string $expression, bool $expected): void
    {
        $this->assertSame($expected, CronSchedule::isValid($expression));
    }

    public function test_is_due_ignores_seconds_and_uses_the_given_timezone(): void
    {
        $tz = new \DateTimeZone('Asia/Shanghai');

        $this->assertTrue(CronSchedule::isDue('0 3 * * *', new \DateTimeImmutable('2026-09-15 03:00:00', $tz)));
        $this->assertTrue(CronSchedule::isDue('0 3 * * *', new \DateTimeImmutable('2026-09-15 03:00:40', $tz)));
        $this->assertFalse(CronSchedule::isDue('0 3 * * *', new \DateTimeImmutable('2026-09-15 03:01:00', $tz)));
        // 同一绝对时刻换成 UTC 表达是 19:00，按 UTC 解释「3 点」就不到点
        $this->assertFalse(CronSchedule::isDue('0 3 * * *', new \DateTimeImmutable('2026-09-14 19:00:00', new \DateTimeZone('UTC'))));
    }

    public function test_is_due_returns_false_for_an_invalid_expression(): void
    {
        $this->assertFalse(CronSchedule::isDue('@hourly', new \DateTimeImmutable('2026-09-15 03:00:00')));
        $this->assertFalse(CronSchedule::isDue('61 * * * *', new \DateTimeImmutable('2026-09-15 03:00:00')));
    }

    public function test_next_run_at_is_strictly_after_now_and_keeps_the_timezone(): void
    {
        $tz = new \DateTimeZone('Asia/Shanghai');

        $next = CronSchedule::nextRunAt('0 3 * * *', new \DateTimeImmutable('2026-09-15 10:20:30', $tz));
        $this->assertSame('2026-09-16 03:00:00', $next->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Shanghai', $next->getTimezone()->getName());

        // 恰好在到点的那一分钟：「下一次」是明天，不是现在
        $this->assertSame('2026-09-16 03:00:00', CronSchedule::nextRunAt('0 3 * * *', new \DateTimeImmutable('2026-09-15 03:00:00', $tz))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15 10:25:00', CronSchedule::nextRunAt('*/5 * * * *', new \DateTimeImmutable('2026-09-15 10:20:30', $tz))->format('Y-m-d H:i:s'));
    }

    public function test_next_run_at_rejects_an_invalid_expression(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CronSchedule::nextRunAt('@daily', new \DateTimeImmutable());
    }
}
