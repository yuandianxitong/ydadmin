<?php

declare(strict_types=1);

namespace tests\Unit\Queue;

use tests\TestCase;

final class QueueConsumerLayoutTest extends TestCase
{
    public function test_groups_have_independent_capacity_and_consumer_directories(): void
    {
        $processes = require base_path('config/plugin/webman/redis-queue/process.php');
        $this->assertSame(['consumer', 'consumer_slow', 'consumer_messages', 'consumer_tasks'], array_keys($processes));
        $directories = array_column(array_column($processes, 'constructor'), 'consumer_dir');
        $this->assertCount(4, array_unique($directories));
        $registered = [];
        foreach ($processes as $process) {
            $this->assertGreaterThanOrEqual(1, $process['count']);
            $directory = $process['constructor']['consumer_dir'];
            $this->assertDirectoryExists($directory);
            foreach (glob($directory . '/*.php') ?: [] as $file) {
                $class = str_replace('/', '\\', substr($file, strlen(base_path()) + 1, -4));
                $reflection = new \ReflectionClass($class);
                $this->assertTrue($reflection->isInstantiable());
                $this->assertTrue($reflection->implementsInterface(\core\queue\DeliveryHandler::class));
                $queue = $reflection->getProperty('queue')->getDefaultValue();
                $this->assertSame($class, config('queue.queues.' . $queue . '.consumer'));
                $registered[] = $queue;
            }
        }
        $expected = array_keys((array) config('queue.queues'));
        sort($expected);
        sort($registered);
        $this->assertSame($expected, $registered);
    }
}
