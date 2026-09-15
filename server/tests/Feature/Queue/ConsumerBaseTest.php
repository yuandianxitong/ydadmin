<?php

declare(strict_types=1);

namespace tests\Feature\Queue;

use core\context\RequestContext;
use core\queue\QueueDispatcher;
use support\Container;
use support\Context;
use support\Db;
use tests\fixtures\Queue\FixtureBaseConsumer;
use tests\Support\ConfigOverride;
use tests\TestCase;

final class ConsumerBaseTest extends TestCase
{
    use ConfigOverride;

    protected function setUp(): void
    {
        parent::setUp();
        Db::table('failed_jobs')->delete();
        FixtureBaseConsumer::$handled = [];
        FixtureBaseConsumer::$actingUserSeen = -1;
        $this->overrideConfig('queue.driver', 'sync');
        $this->overrideConfig('queue.queues.fixture-base', ['consumer' => FixtureBaseConsumer::class, 'max_attempts' => 3]);
    }

    protected function tearDown(): void
    {
        Db::table('failed_jobs')->delete();
        $this->restoreConfig();
        parent::tearDown();
    }

    private function consumer(): FixtureBaseConsumer
    {
        return Container::get(FixtureBaseConsumer::class);
    }

    public function test_a_failure_before_the_last_attempt_is_not_recorded(): void
    {
        $package = $this->consumer()->onConsumeFailure(
            new \RuntimeException('boom'),
            ['queue' => 'fixture-base', 'data' => ['id' => 1], 'attempts' => 1]
        );

        $this->assertSame(3, $package['max_attempts'], '必须把本队列登记的 max_attempts 写回包裹，否则库会用全局兜底值 5');
        $this->assertSame(0, Db::table('failed_jobs')->count());
    }

    public function test_the_last_failure_is_recorded_with_a_masked_payload(): void
    {
        // attempts=3，库随后会 ++ 到 4 > 3，所以这就是最后一次
        $this->consumer()->onConsumeFailure(
            new \RuntimeException('boom'),
            ['queue' => 'fixture-base', 'data' => ['name' => '甲', 'password' => 'Secret#1'], 'attempts' => 3]
        );

        $row = Db::table('failed_jobs')->first();
        $this->assertNotNull($row);
        $this->assertSame('fixture-base', $row->queue);
        $this->assertSame(4, (int) $row->attempts);
        $payload = json_decode((string) $row->payload, true);
        ksort($payload);
        $this->assertSame(['name' => '甲', 'password' => '***'], $payload);
        $this->assertStringStartsWith('RuntimeException: boom', (string) $row->exception);
    }

    public function test_an_unregistered_queue_treats_the_first_failure_as_the_last(): void
    {
        $package = $this->consumer()->onConsumeFailure(
            new \RuntimeException('boom'),
            ['queue' => 'fixture-unregistered', 'data' => ['id' => 1], 'attempts' => 0]
        );

        $this->assertSame(0, $package['max_attempts']);
        $this->assertSame(1, Db::table('failed_jobs')->where('queue', 'fixture-unregistered')->count());
    }

    public function test_sync_dispatch_failure_goes_straight_to_failed_jobs(): void
    {
        Container::get(QueueDispatcher::class)->dispatch('fixture-base', ['id' => 8, 'fail' => true]);

        $this->assertSame([['id' => 8, 'fail' => true]], FixtureBaseConsumer::$handled);
        $row = Db::table('failed_jobs')->where('queue', 'fixture-base')->first();
        $this->assertNotNull($row, 'sync 下失败不重试，直接落表');
        $this->assertSame(4, (int) $row->attempts, 'sync 包裹 attempts = max_attempts(3)，落表记 +1');
    }

    public function test_consume_destroys_the_context_after_each_job(): void
    {
        Context::set('queue_probe', 'from-previous-request');

        $this->consumer()->consume(['id' => 1, 'acting_user' => 7]);

        $this->assertNull(Context::get('queue_probe'));
        $this->assertSame(0, RequestContext::actingUser(), '任务里设置的操作人不能留到任务之后');

        $this->consumer()->consume(['id' => 2]);
        $this->assertSame(0, FixtureBaseConsumer::$actingUserSeen, '下一个任务进入 handle() 时看不到上一个任务的操作人');
    }

    public function test_consume_destroys_the_context_even_when_the_job_throws(): void
    {
        try {
            $this->consumer()->consume(['id' => 1, 'acting_user' => 9, 'fail' => true]);
            $this->fail('consume() 必须把异常抛给 redis-queue，由库决定重试');
        } catch (\RuntimeException $e) {
            $this->assertSame('fixture failure', $e->getMessage());
        }

        $this->assertSame(0, RequestContext::actingUser());
    }
}
