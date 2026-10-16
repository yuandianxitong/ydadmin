<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\queue\redis_tasks\BackgroundTaskConsumer;
use app\service\operations\OutboxService;
use core\auth\AuthorizationVersion;
use core\context\RequestContext;
use core\observability\Heartbeat;
use core\queue\QueueDispatcher;
use support\Container;
use support\Db;
use support\Redis;
use tests\fixtures\Queue\RecordingConsumer;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

final class OperationsTest extends ApiTestCase
{
    use ConfigOverride;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir() . '/yd-tasks-' . bin2hex(random_bytes(4));
        $this->overrideConfig('operations.task_storage', $this->storage);
        $this->overrideConfig('operations.inline_delivery', false);
        $this->overrideConfig('queue.queues.phase-two-record', ['consumer' => RecordingConsumer::class, 'max_attempts' => 2]);
        RecordingConsumer::reset();
    }

    protected function tearDown(): void
    {
        Db::table('queue_outbox')->where('queue', 'phase-two-record')->delete();
        foreach (glob($this->storage . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->storage)) {
            rmdir($this->storage);
        }
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_reliable_enqueue_rolls_back_and_repeated_wakeups_are_consumed_once(): void
    {
        $outbox = Container::get(OutboxService::class);
        try {
            Db::connection()->transaction(function () use ($outbox): void {
                $outbox->enqueue('phase-two-record', ['id' => 1], 'rollback');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, Db::table('queue_outbox')->where('queue', 'phase-two-record')->count());
        RequestContext::initTrace('phase-two-trace');
        $id = $outbox->enqueue('phase-two-record', ['id' => 2], 'once');
        $this->assertSame($id, $outbox->enqueue('phase-two-record', ['id' => 2], 'once'));
        $outbox->publishOne($id);
        Container::get(QueueDispatcher::class)->dispatch('phase-two-record', ['__delivery_id' => $id]);
        $this->assertSame([['id' => 2]], RecordingConsumer::$handled);
        $row = Db::table('queue_outbox')->where('id', $id)->first();
        $this->assertSame(3, (int) $row->status);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertSame('phase-two-trace', $row->trace_id);
    }

    public function test_balance_adjustment_replay_is_atomic_and_payload_conflicts_are_rejected(): void
    {
        $admin = $this->actingAsAdmin(['user.adjust-balance']);
        $user = $this->actingAsUser();
        $body = ['user_id' => $user->id, 'amount' => '10.00', 'remark' => 'idem'];
        $headers = ['Idempotency-Key' => 'balance-adjust-123'];
        $this->post('/adminapi/user/adjust-balance', $body, $admin->token, $headers)->assertOk();
        $this->post('/adminapi/user/adjust-balance', $body, $admin->token, $headers)->assertOk();
        $this->post('/adminapi/user/adjust-balance', array_replace($body, ['amount' => '20.00']), $admin->token, $headers)->assertCode(409);
        $this->assertSame('10.00', Db::table('users')->where('id', $user->id)->value('balance'));
        $this->assertSame(1, Db::table('balance_logs')->where('user_id', $user->id)->count());
        $roleId = Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
        Db::connection()->transaction(function () use ($roleId): void {
            Db::table('role_menus')->where('role_id', $roleId)->delete();
            AuthorizationVersion::bump();
        });
        $this->post('/adminapi/user/adjust-balance', $body, $admin->token, $headers)->assertCode(403);
        Db::table('http_idempotency')->where('actor_id', $admin->id)->delete();
    }

    public function test_expired_delivery_owner_cannot_acknowledge_or_fail_the_new_owner(): void
    {
        $outbox = Container::get(OutboxService::class);
        $id = $outbox->enqueue('phase-two-record', ['value' => 1]);
        $first = $outbox->claim($id, 'phase-two-record');
        Db::table('queue_outbox')->where('id', $id)->update(['lease_until' => 0]);
        $second = $outbox->claim($id, 'phase-two-record');
        $this->assertNotSame($first['lease_token'], $second['lease_token']);
        $this->assertFalse($outbox->fail($id, $first['lease_token'], new \core\queue\PermanentJobFailure('stale')));
        try {
            $outbox->complete($id, $first['lease_token']);
            $this->fail('Stale owner must not acknowledge the replacement delivery');
        } catch (\core\queue\PermanentJobFailure) {
        }
        $outbox->complete($id, $second['lease_token']);
        $this->assertSame(3, (int) Db::table('queue_outbox')->where('id', $id)->value('status'));
    }

    public function test_transient_publish_failure_preserves_a_recoverable_outbox_record(): void
    {
        $dispatcher = new class () extends QueueDispatcher {
            public bool $unavailable = true;
            public array $sent = [];
            public function dispatch(string $queue, array $data): void
            {
                if ($this->unavailable) {
                    throw new \RuntimeException('Redis unavailable');
                }
                $this->sent[] = $data;
            }
        };
        $service = Container::make(OutboxService::class);
        (new \ReflectionProperty(OutboxService::class, 'dispatcher'))->setValue($service, $dispatcher);
        $id = $service->enqueue('phase-two-record', ['value' => 1]);
        try {
            $service->publishOne($id);
            $this->fail('A failed transport must throw');
        } catch (\RuntimeException) {
        }
        $this->assertNotSame(3, (int) Db::table('queue_outbox')->where('id', $id)->value('status'));
        Db::table('queue_outbox')->where('id', $id)->update(['lease_until' => 0]);
        $dispatcher->unavailable = false;
        $service->publishOne($id);
        $this->assertSame([['__delivery_id' => $id]], $dispatcher->sent);
        $this->assertSame(1, (int) Db::table('queue_outbox')->where('id', $id)->value('status'), 'Transport success still requires a consumer acknowledgement');
    }

    /** @return array<string, mixed> */
    private function exportTask(string $token): array
    {
        $task = $this->post('/adminapi/tasks/export', ['module' => 'article'], $token)->assertOk()->data();
        $row = Db::table('background_tasks')->where('public_id', $task['task_id'])->first();
        $this->track('background_tasks', (int) $row->id);
        $delivery = Db::table('queue_outbox')->where('queue', 'background-task')->where('payload->task_id', (int) $row->id)->first();
        $this->track('queue_outbox', (int) $delivery->id);
        $task['delivery_id'] = (int) $delivery->id;

        return $task;
    }

    public function test_async_export_is_private_cancellable_and_owned_by_its_submitter(): void
    {
        $admin = $this->actingAsAdmin(['article.list']);
        $other = $this->actingAsAdmin('super');
        $task = $this->exportTask($admin->token);
        $this->assertSame('queued', $task['status']);
        $this->get('/adminapi/tasks/' . $task['task_id'], [], $other->token)->assertCode(404);
        $this->post('/adminapi/tasks/' . $task['task_id'] . '/cancel', [], $admin->token)->assertOk();
        Container::get(BackgroundTaskConsumer::class)->consume(['__delivery_id' => $task['delivery_id']]);
        $this->assertSame('cancelled', $this->get('/adminapi/tasks/' . $task['task_id'], [], $admin->token)->assertOk()->data()['status']);
        $this->get('/adminapi/tasks/' . $task['task_id'] . '/download', [], $admin->token)->assertCode(409);

        $task = $this->exportTask($admin->token);
        Container::get(BackgroundTaskConsumer::class)->consume(['__delivery_id' => $task['delivery_id']]);
        $result = $this->get('/adminapi/tasks/' . $task['task_id'], [], $admin->token)->assertOk()->data();
        $this->assertSame('completed', $result['status']);
        $this->assertTrue($result['download_ready']);
        $this->assertArrayNotHasKey('file', $result['result']);
        $this->assertStringNotContainsString($this->storage, json_encode($result));
        $this->get('/adminapi/tasks/' . $task['task_id'] . '/download', [], $admin->token)->assertStatus(200);
    }

    public function test_worker_rechecks_permissions_before_executing_export(): void
    {
        $admin = $this->actingAsAdmin(['article.list']);
        $task = $this->exportTask($admin->token);
        $roleId = Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
        Db::connection()->transaction(function () use ($roleId): void {
            Db::table('role_menus')->where('role_id', $roleId)->delete();
            AuthorizationVersion::bump();
        });
        Container::get(BackgroundTaskConsumer::class)->consume(['__delivery_id' => $task['delivery_id']]);
        $this->assertSame('failed', Db::table('background_tasks')->where('public_id', $task['task_id'])->value('status'));
        $this->assertSame([], glob($this->storage . '/*') ?: []);
        Db::table('failed_jobs')->where('queue', 'background-task')->delete();
    }

    public function test_export_idempotency_replay_rechecks_the_module_permission(): void
    {
        $admin = $this->actingAsAdmin(['article.list']);
        $body = ['module' => 'article'];
        $headers = ['Idempotency-Key' => 'export-replay-permission'];
        $first = $this->post('/adminapi/tasks/export', $body, $admin->token, $headers)->assertOk()->data();
        $second = $this->post('/adminapi/tasks/export', $body, $admin->token, $headers)->assertOk()->data();
        $this->assertSame($first['task_id'], $second['task_id']);
        $id = (int) Db::table('background_tasks')->where('public_id', $first['task_id'])->value('id');
        $this->track('background_tasks', $id);
        $this->track('queue_outbox', (int) Db::table('queue_outbox')->where('queue', 'background-task')->where('payload->task_id', $id)->value('id'));
        $roleId = Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
        Db::connection()->transaction(function () use ($roleId): void {
            Db::table('role_menus')->where('role_id', $roleId)->delete();
            AuthorizationVersion::bump();
        });
        $this->post('/adminapi/tasks/export', $body, $admin->token, $headers)->assertCode(403);
        Db::table('http_idempotency')->where('actor_id', $admin->id)->delete();
    }

    public function test_import_resumes_after_a_committed_row_without_importing_it_twice(): void
    {
        $admin = $this->actingAsAdmin(['user.import']);
        $mobile1 = '139' . random_int(10000000, 99999999);
        $mobile2 = '138' . random_int(10000000, 99999999);
        $csv = "mobile,nickname\n{$mobile1},first\n{$mobile2},second\n\n";
        $task = $this->postFile('/adminapi/tasks/import-user', 'file', 'users.csv', $csv, 'text/csv', $admin->token)->assertOk()->data();
        $row = Db::table('background_tasks')->where('public_id', $task['task_id'])->first();
        $id = (int) $row->id;
        $this->track('background_tasks', $id);
        $deliveryId = (int) Db::table('queue_outbox')->where('queue', 'background-task')->where('payload->task_id', $id)->value('id');
        $this->track('queue_outbox', $deliveryId);
        $firstClaim = Container::get(OutboxService::class)->claim($deliveryId, 'background-task');
        // 模拟 worker 在首行数据与检查点一起提交后退出。
        $this->actingAsUser(['mobile' => $mobile1, 'nickname' => 'first']);
        Db::table('background_tasks')->where('id', $id)->update(['status' => 'running', 'worker_token' => $firstClaim['lease_token'], 'cursor' => 1, 'processed' => 1, 'success_count' => 1]);
        Db::table('queue_outbox')->where('id', $deliveryId)->update(['lease_until' => 0]);
        Container::get(BackgroundTaskConsumer::class)->consume(['__delivery_id' => $deliveryId]);
        $result = $this->get('/adminapi/tasks/' . $task['task_id'], [], $admin->token)->assertOk()->data();
        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, $result['processed']);
        $this->assertSame(2, $result['success_count']);
        $this->assertSame(0, $result['fail_count']);
        $this->assertSame(1, Db::table('users')->where('mobile', $mobile1)->count());
        $this->assertSame(1, Db::table('users')->where('mobile', $mobile2)->count());
        $this->trackUser((int) Db::table('users')->where('mobile', $mobile2)->value('id'));
        $this->track('data_imports', (int) $result['result']['import_id']);
        $this->assertSame([], glob($this->storage . '/*.csv') ?: []);
    }

    public function test_import_row_limit_is_checked_before_any_data_is_written(): void
    {
        $this->overrideConfig('operations.task_max_rows', 1);
        $admin = $this->actingAsAdmin(['user.import']);
        $mobile = '137' . random_int(10000000, 99999999);
        $task = $this->postFile('/adminapi/tasks/import-user', 'file', 'users.csv', "mobile\n{$mobile}\n{$mobile}\n", 'text/csv', $admin->token)->assertOk()->data();
        $id = (int) Db::table('background_tasks')->where('public_id', $task['task_id'])->value('id');
        $this->track('background_tasks', $id);
        $deliveryId = (int) Db::table('queue_outbox')->where('queue', 'background-task')->where('payload->task_id', $id)->value('id');
        $this->track('queue_outbox', $deliveryId);
        Container::get(BackgroundTaskConsumer::class)->consume(['__delivery_id' => $deliveryId]);
        $this->assertSame('failed', Db::table('background_tasks')->where('id', $id)->value('status'));
        $this->assertSame(0, Db::table('users')->where('mobile', $mobile)->count());
        $this->assertSame([], glob($this->storage . '/*.csv') ?: []);
        Db::table('failed_jobs')->where('queue', 'background-task')->delete();
    }

    public function test_readiness_reports_stale_workers_and_monitoring_requires_super_admin(): void
    {
        $this->overrideConfig('operations.require_workers_ready', true);
        foreach (Heartbeat::GROUPS as $group) {
            Redis::del('ops:heartbeat:' . $group);
        }
        $this->get('/adminapi/health/ready')->assertStatus(503)->assertCode(503);
        foreach (Heartbeat::GROUPS as $group) {
            Container::get(Heartbeat::class)->beat($group);
        }
        $ready = $this->get('/adminapi/health/ready')->assertOk()->data();
        $this->assertTrue($ready['ready']);
        $this->assertSame(['database', 'redis', 'workers', 'storage'], array_keys($ready['checks']));
        $admin = $this->actingAsAdmin();
        $this->get('/adminapi/system/monitoring', [], $admin->token)->assertCode(403);
        $super = $this->actingAsAdmin('super');
        $monitor = $this->get('/adminapi/system/monitoring', [], $super->token)->assertOk()->data();
        $this->assertArrayHasKey('oldest_pending_seconds', $monitor['outbox']);
        $this->assertArrayHasKey('background-task', $monitor['queues']);
    }
}
