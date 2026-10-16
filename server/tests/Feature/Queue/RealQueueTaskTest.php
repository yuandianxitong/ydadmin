<?php

declare(strict_types=1);

namespace tests\Feature\Queue;

use app\service\operations\OutboxService;
use core\queue\QueueDispatcher;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

final class RealQueueTaskTest extends ApiTestCase
{
    use ConfigOverride;

    public function test_two_real_workers_consume_duplicate_wakeups_once_and_publish_a_private_export(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('proc_open')) {
            $this->markTestSkipped('Real queue worker requires process support');
        }
        $root = sys_get_temp_dir() . '/yd-real-queue-' . bin2hex(random_bytes(4));
        mkdir($root, 0o700);
        $storage = $root . '/files';
        $process = null;
        try {
            $this->overrideConfig('operations.task_storage', $storage);
            $this->overrideConfig('operations.inline_delivery', false);
            $this->overrideConfig('queue.driver', 'redis');
            $admin = $this->actingAsAdmin(['article.list']);
            $task = $this->post('/adminapi/tasks/export', ['module' => 'article'], $admin->token, ['X-Trace-Id' => 'real-queue-trace'])->assertOk()->data();
            $row = Db::table('background_tasks')->where('public_id', $task['task_id'])->first();
            $id = (int) $row->id;
            $this->track('background_tasks', $id);
            $deliveryId = (int) Db::table('queue_outbox')->where('queue', 'background-task')->where('payload->task_id', $id)->value('id');
            $this->track('queue_outbox', $deliveryId);
            Container::get(OutboxService::class)->publishOne($deliveryId);
            Container::get(QueueDispatcher::class)->dispatch('background-task', ['__delivery_id' => $deliveryId]);
            $environment = getenv();
            $environment['YD_QUEUE_TEST_DATABASE'] = (string) config('database.connections.mysql.database');
            $environment['YD_QUEUE_TEST_STORAGE'] = $storage;
            $environment['YD_QUEUE_TEST_RUNTIME'] = $root;
            $process = proc_open([PHP_BINARY, base_path('tests/Support/RealQueueWorker.php')], [0 => ['pipe', 'r'], 1 => ['file', $root . '/output.log', 'a'], 2 => ['file', $root . '/output.log', 'a']], $pipes, base_path(), $environment);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $deadline = microtime(true) + 20;
            do {
                usleep(100000);
                $state = Db::table('background_tasks')->where('id', $id)->value('status');
                $deliveryState = (int) Db::table('queue_outbox')->where('id', $deliveryId)->value('status');
            } while (($state !== 'completed' || $deliveryState !== 3) && microtime(true) < $deadline);
            $this->assertSame('completed', $state, (string) file_get_contents($root . '/output.log'));
            $delivery = Db::table('queue_outbox')->where('id', $deliveryId)->first();
            $this->assertSame(3, (int) $delivery->status);
            $this->assertSame(1, (int) $delivery->attempts);
            $this->assertSame('real-queue-trace', $delivery->trace_id);
            $files = glob($storage . '/*.csv') ?: [];
            $this->assertCount(1, $files);
            $this->assertStringContainsString('category_id', (string) file_get_contents($files[0]));
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            $this->restoreConfig();
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }

    public function test_parallel_idempotency_requests_commit_one_business_change(): void
    {
        $root = sys_get_temp_dir() . '/yd-idem-' . bin2hex(random_bytes(4));
        mkdir($root, 0o700);
        $processes = [];
        $admin = $this->actingAsAdmin('super');
        $user = $this->actingAsUser(['points' => 0]);
        try {
            $environment = getenv();
            $environment['YD_QUEUE_TEST_DATABASE'] = (string) config('database.connections.mysql.database');
            $environment['YD_QUEUE_TEST_STORAGE'] = $root;
            $environment['YD_QUEUE_TEST_RUNTIME'] = $root;
            $environment['YD_TEST_IDEMPOTENCY_KEY'] = 'parallel-' . bin2hex(random_bytes(8));
            $environment['YD_TEST_ACTOR_ID'] = (string) $admin->id;
            $environment['YD_TEST_USER_ID'] = (string) $user->id;
            for ($index = 0; $index < 2; $index++) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/RealQueueWorker.php')], [0 => ['pipe', 'r'], 1 => ['file', $root . '/output.log', 'a'], 2 => ['file', $root . '/output.log', 'a']], $pipes, base_path(), $environment);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertSame(0, proc_close($process), (string) file_get_contents($root . '/output.log'));
            }
            $processes = [];
            $this->assertSame(1, (int) Db::table('users')->where('id', $user->id)->value('points'));
            $responses = glob($root . '/*.json') ?: [];
            $this->assertCount(2, $responses);
            $this->assertSame(file_get_contents($responses[0]), file_get_contents($responses[1]));
        } finally {
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            Db::table('http_idempotency')->where('actor_id', $admin->id)->delete();
            foreach (glob($root . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($root);
        }
    }
}
