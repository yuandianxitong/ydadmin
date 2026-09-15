<?php

declare(strict_types=1);

namespace tests\Unit\Process;

use app\process\Scheduler;
use app\service\system\CronScheduleService;
use tests\TestCase;

/** 记下 tick 被调用的次数；$throw 为真时模拟数据库断线。不经容器构造，#[Inject] 属性不会被访问。 */
final class CountingScheduleService extends CronScheduleService
{
    public int $calls = 0;

    public bool $throw = false;

    public function tick(\DateTimeImmutable $now, ?\DateTimeImmutable $lastMinute): \DateTimeImmutable
    {
        $this->calls++;
        if ($this->throw) {
            throw new \RuntimeException('模拟数据库断线');
        }

        return $now->setTime((int) $now->format('H'), (int) $now->format('i'));
    }
}

final class SchedulerTest extends TestCase
{
    public function test_tick_runs_once_per_minute(): void
    {
        $service = new CountingScheduleService();
        $scheduler = new Scheduler($service);

        $this->assertTrue($scheduler->tickIfMinuteChanged(new \DateTimeImmutable('2026-09-15 10:00:01')));
        $this->assertFalse($scheduler->tickIfMinuteChanged(new \DateTimeImmutable('2026-09-15 10:00:02')));
        $this->assertFalse($scheduler->tickIfMinuteChanged(new \DateTimeImmutable('2026-09-15 10:00:59')));
        $this->assertSame(1, $service->calls, '同一分钟内 1 秒定时器触发 60 次，只调用一次 tick');

        $this->assertTrue($scheduler->tickIfMinuteChanged(new \DateTimeImmutable('2026-09-15 10:01:00')));
        $this->assertSame(2, $service->calls);
    }

    public function test_a_failing_tick_never_escapes_and_is_retried_next_minute(): void
    {
        $service = new CountingScheduleService();
        $service->throw = true;
        $scheduler = new Scheduler($service);

        // 不抛出：Timer 回调里的未捕获异常会让 scheduler 进程退出
        $this->assertFalse($scheduler->tickIfMinuteChanged(new \DateTimeImmutable('2026-09-15 10:00:01')));
        $this->assertFalse($scheduler->tickIfMinuteChanged(new \DateTimeImmutable('2026-09-15 10:00:02')));
        $this->assertSame(1, $service->calls, '失败的这一分钟不每秒重试（否则断库时每分钟刷 60 条错误日志）');

        $service->throw = false;
        $this->assertTrue($scheduler->tickIfMinuteChanged(new \DateTimeImmutable('2026-09-15 10:01:00')));
        $this->assertSame(2, $service->calls, '下一分钟重试');
    }
}
