<?php

declare(strict_types=1);

namespace tests\Unit\Queue;

use core\queue\QueueDispatcher;
use support\Container;
use support\Context;
use support\Redis;
use tests\fixtures\Queue\PlainConsumer;
use tests\fixtures\Queue\RecordingConsumer;
use tests\fixtures\Queue\ThrowingConsumer;
use tests\Support\ConfigOverride;
use tests\TestCase;

final class QueueDispatcherTest extends TestCase
{
    use ConfigOverride;

    /** redis-queue 的等待队列键：'{redis-queue}-waiting' . 队列名（prefix 为空） */
    private const WAITING_KEY = '{redis-queue}-waitingfixture-redis';

    protected function setUp(): void
    {
        parent::setUp();
        RecordingConsumer::$handled = [];
        ThrowingConsumer::$failures = [];
        $this->overrideConfig('queue.driver', 'sync');
    }

    protected function tearDown(): void
    {
        Redis::del(self::WAITING_KEY);
        $this->restoreConfig();
        parent::tearDown();
    }

    private function dispatcher(): QueueDispatcher
    {
        return Container::get(QueueDispatcher::class);
    }

    public function test_sync_driver_calls_handle_with_json_round_tripped_data(): void
    {
        $this->overrideConfig('queue.queues.fixture-record', ['consumer' => RecordingConsumer::class, 'max_attempts' => 2]);

        $this->dispatcher()->dispatch('fixture-record', ['id' => 7, 'name' => '甲', 'nested' => ['ok' => true]]);

        $this->assertSame([['id' => 7, 'name' => '甲', 'nested' => ['ok' => true]]], RecordingConsumer::$handled);
    }

    public function test_sync_driver_keeps_the_current_request_context(): void
    {
        // 设计决定 3：sync 调 handle() 而不是 consume()，否则同步投递会把当次请求的上下文整个抹掉
        $this->overrideConfig('queue.queues.fixture-record', ['consumer' => RecordingConsumer::class, 'max_attempts' => 0]);
        Context::set('queue_probe', 'still-here');

        $this->dispatcher()->dispatch('fixture-record', ['id' => 1]);

        $this->assertSame('still-here', Context::get('queue_probe'));
    }

    public function test_unregistered_queue_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->dispatcher()->dispatch('fixture-not-registered', ['id' => 1]);
    }

    public function test_consumer_that_is_not_a_queue_handler_is_rejected(): void
    {
        $this->overrideConfig('queue.queues.fixture-plain', ['consumer' => PlainConsumer::class, 'max_attempts' => 0]);

        $this->expectException(\InvalidArgumentException::class);

        $this->dispatcher()->dispatch('fixture-plain', ['id' => 1]);
    }

    public function test_sync_failure_is_handed_to_on_consume_failure_as_the_last_attempt_and_not_rethrown(): void
    {
        $this->overrideConfig('queue.queues.fixture-throw', ['consumer' => ThrowingConsumer::class, 'max_attempts' => 3]);

        $this->dispatcher()->dispatch('fixture-throw', ['id' => 9]);

        $this->assertCount(1, ThrowingConsumer::$failures);
        $failure = ThrowingConsumer::$failures[0];
        $this->assertSame('boom', $failure['message']);
        $this->assertSame('fixture-throw', $failure['package']['queue']);
        $this->assertSame(['id' => 9], $failure['package']['data']);
        // 设计决定 5：attempts = max_attempts，ConsumerBase 的「attempts + 1 > max_attempts」恒为真
        $this->assertSame(3, $failure['package']['attempts']);
        $this->assertSame(3, $failure['package']['max_attempts']);
    }

    public function test_redis_driver_pushes_a_redis_queue_package_to_the_waiting_list(): void
    {
        $this->overrideConfig('queue.driver', 'redis');
        $this->overrideConfig('queue.queues.fixture-redis', ['consumer' => RecordingConsumer::class, 'max_attempts' => 0]);
        Redis::del(self::WAITING_KEY);

        $this->dispatcher()->dispatch('fixture-redis', ['id' => 5, 'name' => '乙']);

        $items = Redis::lrange(self::WAITING_KEY, 0, -1);
        $this->assertCount(1, $items, '测试 Redis 是 DB 15：插件连接配置必须跟随 REDIS_DB');
        $package = json_decode((string) $items[0], true);
        $this->assertSame('fixture-redis', $package['queue']);
        $this->assertSame(['id' => 5, 'name' => '乙'], $package['data']);
        $this->assertSame(0, $package['attempts']);
        $this->assertSame([], RecordingConsumer::$handled, 'redis 驱动只投递，不在当前进程消费');
    }
}
