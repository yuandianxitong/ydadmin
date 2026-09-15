<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\queue\redis\OperationLogConsumer;
use core\queue\QueueDispatcher;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

final class OperationLogConsumerTest extends ApiTestCase
{
    /** @return array<string, mixed> */
    private function payload(int $adminId, string $operationTime): array
    {
        return [
            'admin_id'       => $adminId,
            'username'       => 'consumer_probe',
            'method'         => 'POST',
            'path'           => '/adminapi/demo',
            'ip'             => '10.0.0.1',
            'user_agent'     => 'phpunit',
            'action'         => '探针',
            'description'    => '消费者写库探针',
            'params'         => ['password' => '***'],
            'result'         => ['code' => 200, 'message' => 'ok'],
            'execution_time' => 0.012,
            'operation_time' => $operationTime,
        ];
    }

    public function test_the_consumer_is_registered_for_the_operation_log_queue(): void
    {
        $this->assertSame('operation-log', Container::get(OperationLogConsumer::class)->queue);
        $this->assertSame(OperationLogConsumer::class, config('queue.queues.operation-log.consumer'));
        $this->assertSame(3, config('queue.queues.operation-log.max_attempts'));
    }

    public function test_handle_writes_the_row_with_the_payload_operation_time(): void
    {
        $admin = $this->actingAsAdmin();

        Container::get(OperationLogConsumer::class)->handle($this->payload($admin->id, '2020-01-02 03:04:05'));

        $row = Db::table('admin_operation_logs')->where('admin_id', $admin->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('2020-01-02 03:04:05', (string) $row->operation_time, '写库必须用载荷里的请求时刻，而不是消费时刻');
        $this->assertSame('/adminapi/demo', $row->path);
        $this->assertSame(['password' => '***'], json_decode((string) $row->params, true));
    }

    public function test_dispatch_through_the_sync_driver_reaches_the_table(): void
    {
        $admin = $this->actingAsAdmin();

        Container::get(QueueDispatcher::class)->dispatch('operation-log', $this->payload($admin->id, '2021-05-06 07:08:09'));

        $this->assertSame(1, Db::table('admin_operation_logs')->where('admin_id', $admin->id)->count());
    }
}
