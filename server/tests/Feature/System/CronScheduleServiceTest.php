<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\CronScheduleService;
use support\Container;
use support\Db;
use support\Redis;
use tests\fixtures\Queue\RecordingConsumer;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

/** 抢锁抛异常的子类：$failFor 里的任务 id 抢锁时模拟 Redis 不可用。 */
final class LockFailingScheduleService extends CronScheduleService
{
    /** @var list<int> */
    public array $failFor = [];

    protected function acquireFireLock(int $id, \DateTimeImmutable $minute): bool
    {
        if (in_array($id, $this->failFor, true)) {
            throw new \RuntimeException('模拟 Redis 不可用');
        }

        return parent::acquireFireLock($id, $minute);
    }
}

/**
 * spec §8.1：调度判定。cron-job 队列的消费者被 ConfigOverride 顶替成 RecordingConsumer，
 * sync 驱动（tests/bootstrap.php 强制）投递即调用它的 handle()，于是 RecordingConsumer::$handled
 * 就是「本次 tick 投递出去的载荷」。时间一律用固定时钟，不依赖测试运行的真实时刻。
 * 种子里的示例任务（0 3 * * *）只在 03:00 到点，本测试的时刻都避开 03 点，且断言只看本用例建的任务。
 */
final class CronScheduleServiceTest extends ApiTestCase
{
    use ConfigOverride;

    /** @var list<int> 本用例建的任务 id */
    private array $jobIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideConfig('queue.queues.cron-job', ['consumer' => RecordingConsumer::class, 'max_attempts' => 0]);
        $this->overrideConfig('cron.catchup_minutes', 5);
        RecordingConsumer::reset();
        $this->forgetFireLocks();
    }

    protected function tearDown(): void
    {
        $this->forgetFireLocks();
        RecordingConsumer::reset();
        $this->restoreConfig();
        $this->jobIds = [];
        parent::tearDown();
    }

    private function forgetFireLocks(): void
    {
        $keys = (array) Redis::keys('cron:fire:*');
        if ($keys !== []) {
            Redis::del(...$keys);
        }
    }

    private function createJob(string $expression, int $status = 1, bool $deleted = false): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('cron_jobs')->insertGetId([
            'name'        => 'sched_' . bin2hex(random_bytes(3)),
            'command'     => 'log:archive --days=3650',
            'expression'  => $expression,
            'description' => '调度测试',
            'status'      => $status,
            'sort'        => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
            'deleted_at'  => $deleted ? $now : null,
        ]);
        $this->track('cron_jobs', $id);
        $this->jobIds[] = $id;

        return $id;
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time);
    }

    /** @return list<array<string, mixed>> 本用例建的任务被投递出去的载荷（按投递顺序） */
    private function dispatched(): array
    {
        return array_values(array_filter(
            RecordingConsumer::$handled,
            fn (array $data): bool => in_array((int) ($data['cron_job_id'] ?? 0), $this->jobIds, true)
        ));
    }

    /** @return list<string> 某个任务被投递的 scheduled_at 列表 */
    private function scheduledAtOf(int $id): array
    {
        return array_values(array_map(
            static fn (array $data): string => (string) $data['scheduled_at'],
            array_filter($this->dispatched(), static fn (array $data): bool => (int) $data['cron_job_id'] === $id)
        ));
    }

    private function service(): CronScheduleService
    {
        return Container::get(CronScheduleService::class);
    }

    public function test_due_job_is_dispatched_with_the_minute_payload_and_the_minute_is_returned(): void
    {
        $id = $this->createJob('0 10 * * *');

        $processed = $this->service()->tick(self::at('2026-09-15 10:00:37'), null);

        $this->assertSame('2026-09-15 10:00:00', $processed->format('Y-m-d H:i:s'), '返回值必须是取整到分钟的当前分钟');
        $this->assertSame([['cron_job_id' => $id, 'trigger' => 1, 'scheduled_at' => '2026-09-15 10:00:00']], $this->dispatched());
    }

    public function test_job_that_is_not_due_is_not_dispatched(): void
    {
        $this->createJob('30 10 * * *');

        $this->service()->tick(self::at('2026-09-15 10:00:05'), null);

        $this->assertSame([], $this->dispatched());
    }

    public function test_disabled_and_soft_deleted_jobs_are_excluded(): void
    {
        $this->createJob('* * * * *', 0);
        $this->createJob('* * * * *', 1, true);

        $this->service()->tick(self::at('2026-09-15 10:00:05'), null);

        $this->assertSame([], $this->dispatched());
    }

    public function test_same_minute_processed_twice_dispatches_once_thanks_to_the_fire_lock(): void
    {
        $id = $this->createJob('* * * * *');

        // 两次都以 null 为上次分钟（模拟进程 reload 后游标丢失、或两台机器同时判定同一分钟）
        $this->service()->tick(self::at('2026-09-15 10:00:05'), null);
        $this->service()->tick(self::at('2026-09-15 10:00:50'), null);

        $this->assertSame(['2026-09-15 10:00:00'], $this->scheduledAtOf($id));
        $this->assertSame(1, (int) Redis::exists("cron:fire:{$id}:202609151000"), '触发锁键必须按 {id}:{YmdHi} 落在 Redis');
    }

    public function test_last_minute_equal_to_current_minute_processes_nothing(): void
    {
        $this->createJob('* * * * *');

        $processed = $this->service()->tick(self::at('2026-09-15 10:00:50'), self::at('2026-09-15 10:00:00'));

        $this->assertSame('2026-09-15 10:00:00', $processed->format('Y-m-d H:i:s'));
        $this->assertSame([], $this->dispatched());
    }

    public function test_gap_within_catchup_is_evaluated_minute_by_minute(): void
    {
        $everyMinute = $this->createJob('* * * * *');
        $atFive = $this->createJob('5 * * * *');
        $atOne = $this->createJob('1 * * * *');

        $processed = $this->service()->tick(self::at('2026-09-15 10:05:10'), self::at('2026-09-15 10:02:00'));

        $this->assertSame('2026-09-15 10:05:00', $processed->format('Y-m-d H:i:s'));
        $this->assertSame(['2026-09-15 10:03:00', '2026-09-15 10:04:00', '2026-09-15 10:05:00'], $this->scheduledAtOf($everyMinute), '缺口 3 分钟：10:03、10:04、10:05 逐分判定');
        $this->assertSame(['2026-09-15 10:05:00'], $this->scheduledAtOf($atFive), '5 * * * * 只在 10:05 到点');
        $this->assertSame([], $this->scheduledAtOf($atOne), '10:01 不在补算区间内（上次已处理到 10:02）');
    }

    public function test_gap_beyond_catchup_evaluates_only_the_current_minute(): void
    {
        $everyMinute = $this->createJob('* * * * *');
        $atSeven = $this->createJob('7 * * * *');

        $processed = $this->service()->tick(self::at('2026-09-15 10:10:20'), self::at('2026-09-15 10:00:00'));

        $this->assertSame('2026-09-15 10:10:00', $processed->format('Y-m-d H:i:s'));
        $this->assertSame(['2026-09-15 10:10:00'], $this->scheduledAtOf($everyMinute), '缺口 10 分钟 > 5：只算当前分钟，不补');
        $this->assertSame([], $this->scheduledAtOf($atSeven), '10:07 被跳过，不补跑');
    }

    public function test_fire_lock_exception_skips_that_job_without_affecting_the_others(): void
    {
        $broken = $this->createJob('* * * * *');
        $healthy = $this->createJob('* * * * *');
        /** @var LockFailingScheduleService $service */
        $service = Container::make(LockFailingScheduleService::class, []);
        $service->failFor = [$broken];

        $service->tick(self::at('2026-09-15 10:00:05'), null);

        $this->assertSame([], $this->scheduledAtOf($broken), '抢锁抛异常：宁可不跑，不投递');
        $this->assertSame(['2026-09-15 10:00:00'], $this->scheduledAtOf($healthy), '一个任务出错不影响同一分钟的其他任务');
    }
}
