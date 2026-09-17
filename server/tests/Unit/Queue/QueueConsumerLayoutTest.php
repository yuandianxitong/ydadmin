<?php

declare(strict_types=1);

namespace tests\Unit\Queue;

use tests\TestCase;

/**
 * M6b 设计决定 13：消费进程按目录分组。consumer 扫 app/queue/redis（操作日志），consumer_slow 扫 app/queue/redis_slow
 * （定时任务、消息发送这类单次可能耗时数秒的任务）。redis-queue 进程会对目录里每个类 Container::get()，
 * 所以目录里只能放可实例化、且队列已登记给自己的消费者。
 */
final class QueueConsumerLayoutTest extends TestCase
{
    private const SLOW_QUEUES = ['cron-job', 'message-send'];

    /** @return array<string, array{handler: string, count: int, constructor: array{consumer_dir: string}}> */
    private static function processes(): array
    {
        return require dirname(__DIR__, 3) . '/config/plugin/webman/redis-queue/process.php';
    }

    public function test_slow_consumer_group_scans_redis_slow_with_the_same_handler(): void
    {
        $processes = self::processes();

        $this->assertSame(['consumer', 'consumer_slow'], array_keys($processes));
        $this->assertSame($processes['consumer']['handler'], $processes['consumer_slow']['handler']);
        $this->assertSame(app_path() . '/queue/redis', $processes['consumer']['constructor']['consumer_dir']);
        $this->assertSame(app_path() . '/queue/redis_slow', $processes['consumer_slow']['constructor']['consumer_dir']);
        $this->assertGreaterThanOrEqual(1, $processes['consumer_slow']['count']);
        $this->assertDirectoryExists(app_path() . '/queue/redis_slow');
    }

    public function test_every_registered_queue_is_consumed_from_its_group_directory(): void
    {
        $queues = (array) config('queue.queues');
        $this->assertSame(['operation-log', 'cron-job', 'message-send'], array_keys($queues));

        foreach ($queues as $queue => $definition) {
            $dir = in_array($queue, self::SLOW_QUEUES, true) ? 'redis_slow' : 'redis';
            $class = (string) $definition['consumer'];
            $this->assertStringStartsWith("app\\queue\\{$dir}\\", $class, "{$queue} 应由 app/queue/{$dir} 下的消费者处理");
            $this->assertTrue(class_exists($class), "{$class} 不存在");
            $this->assertSame($queue, (new \ReflectionClass($class))->getProperty('queue')->getDefaultValue());
        }
    }

    public function test_consumer_directories_hold_only_instantiable_consumers_of_registered_queues(): void
    {
        foreach (['redis', 'redis_slow'] as $dir) {
            $files = glob(app_path() . "/queue/{$dir}/*.php") ?: [];
            $this->assertNotSame([], $files, "app/queue/{$dir} 不应为空");
            foreach ($files as $file) {
                $class = "app\\queue\\{$dir}\\" . basename($file, '.php');
                $reflection = new \ReflectionClass($class);
                $this->assertTrue($reflection->isInstantiable(), "{$class} 必须可实例化，否则消费进程启动即崩");
                $this->assertTrue($reflection->implementsInterface(\Webman\RedisQueue\Consumer::class), "{$class} 不是 redis-queue 消费者");
                $queue = (string) $reflection->getProperty('queue')->getDefaultValue();
                $this->assertSame($class, config("queue.queues.{$queue}.consumer"), "{$class} 订阅的 {$queue} 未登记或登记给了别的类");
            }
        }
    }
}
