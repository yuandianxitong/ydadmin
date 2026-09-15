<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\CronJobService;
use support\Container;
use support\Db;
use support\Redis;
use tests\fixtures\Cron\FixtureCommand;
use tests\fixtures\Queue\NoopConsumer;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

/**
 * 手动执行与队列执行（spec §8.2、§8.3）。测试进程的队列驱动是 sync：投递即同步调 CronJobConsumer::handle()，
 * 结果先 LPUSH 进 Redis，runNow() 的 BLPOP 立即取到——与线上走的是同一套代码。
 */
final class CronJobRunTest extends ApiTestCase
{
    use ConfigOverride;

    /** @var list<int> */
    private array $jobIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $commands = (array) config('cron.commands', []);
        $commands['fixture:cron'] = FixtureCommand::class;
        $this->overrideConfig('cron.commands', $commands);
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        if ($this->jobIds !== []) {
            Db::table('cron_job_logs')->whereIn('cron_job_id', $this->jobIds)->delete();
            foreach ($this->jobIds as $id) {
                Redis::del("cron:running:{$id}");
            }
        }
        $this->jobIds = [];
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function insertJob(string $command, array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('cron_jobs')->insertGetId(array_merge([
            'name'       => '执行测试' . bin2hex(random_bytes(3)),
            'command'    => $command,
            'expression' => '* * * * *',
            'status'     => 1,
            'sort'       => 0,
            'run_count'  => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->track('cron_jobs', $id);
        $this->jobIds[] = $id;

        return $id;
    }

    /** @return list<object> */
    private function logsOf(int $jobId): array
    {
        return Db::table('cron_job_logs')->where('cron_job_id', $jobId)->orderByDesc('id')->get()->all();
    }

    public function test_run_requires_permission_and_404_for_missing_job(): void
    {
        $id = $this->insertJob('fixture:cron hello');
        $nobody = $this->actingAsAdmin(['system.cron_job.list']);
        $runner = $this->actingAsAdmin(['system.cron_job.run']);

        $this->post("/adminapi/system/cron-job/{$id}/run", [], $nobody->token)->assertCode(403);
        $this->post('/adminapi/system/cron-job/999999999/run', [], $runner->token)->assertCode(404);
        $this->assertSame([], $this->logsOf($id));
    }

    public function test_successful_run_returns_output_writes_a_manual_log_and_updates_the_job(): void
    {
        $id = $this->insertJob('fixture:cron hello-cron');
        $admin = $this->actingAsAdmin(['system.cron_job.run']);

        $data = $this->post("/adminapi/system/cron-job/{$id}/run", [], $admin->token)->assertOk()->data();

        $this->assertSame(1, $data['status']);
        $this->assertStringContainsString('hello-cron', $data['output']);

        $logs = $this->logsOf($id);
        $this->assertCount(1, $logs);
        $this->assertSame(CronJobService::TRIGGER_MANUAL, (int) $logs[0]->trigger);
        $this->assertSame(1, (int) $logs[0]->status);
        $this->assertStringContainsString('hello-cron', (string) $logs[0]->output);
        $this->assertNotNull($logs[0]->started_at);
        $this->assertNotNull($logs[0]->finished_at);
        $this->assertGreaterThanOrEqual(0, (int) $logs[0]->duration);

        $job = Db::table('cron_jobs')->where('id', $id)->first();
        $this->assertSame(1, (int) $job->run_count);
        $this->assertSame(1, (int) $job->last_status);
        $this->assertNotNull($job->last_run_at);
        $this->assertStringContainsString('hello-cron', (string) $job->last_result);

        $this->assertSame(0, (int) Redis::exists("cron:running:{$id}"), '执行结束必须释放执行锁');
    }

    public function test_non_zero_exit_is_reported_as_failure(): void
    {
        $id = $this->insertJob('fixture:cron partial --fail');
        $admin = $this->actingAsAdmin(['system.cron_job.run']);

        $data = $this->post("/adminapi/system/cron-job/{$id}/run", [], $admin->token)->assertOk()->data();

        $this->assertSame(0, $data['status']);
        $logs = $this->logsOf($id);
        $this->assertCount(1, $logs);
        $this->assertSame(0, (int) $logs[0]->status);
        $this->assertSame(0, (int) Db::table('cron_jobs')->where('id', $id)->value('last_status'));
        $this->assertSame(1, (int) Db::table('cron_jobs')->where('id', $id)->value('run_count'));
        $this->assertSame(0, (int) Redis::exists("cron:running:{$id}"));
    }

    public function test_exception_is_recorded_in_the_error_column(): void
    {
        $id = $this->insertJob('fixture:cron --throw');
        $admin = $this->actingAsAdmin(['system.cron_job.run']);

        $data = $this->post("/adminapi/system/cron-job/{$id}/run", [], $admin->token)->assertOk()->data();

        $this->assertSame(0, $data['status']);
        $this->assertStringContainsString('夹具命令按要求抛出异常', $data['output']);
        $log = $this->logsOf($id)[0];
        $this->assertSame(0, (int) $log->status);
        $this->assertStringContainsString('夹具命令按要求抛出异常', (string) $log->error);
        $this->assertStringContainsString('夹具命令按要求抛出异常', (string) Db::table('cron_jobs')->where('id', $id)->value('last_result'));
        $this->assertSame(0, (int) Redis::exists("cron:running:{$id}"), '命令抛异常也必须释放执行锁');
    }

    public function test_held_lock_returns_running_message_writes_nothing_and_keeps_the_foreign_lock(): void
    {
        $id = $this->insertJob('fixture:cron hello');
        $admin = $this->actingAsAdmin(['system.cron_job.run']);
        Redis::set("cron:running:{$id}", 'another-worker-token', 'EX', 60, 'NX');

        $data = $this->post("/adminapi/system/cron-job/{$id}/run", [], $admin->token)->assertOk()->data();

        $this->assertSame(['status' => 0, 'output' => lang('business.cron_job_running')], $data);
        $this->assertSame([], $this->logsOf($id));
        $this->assertSame(0, (int) Db::table('cron_jobs')->where('id', $id)->value('run_count'));
        $this->assertSame('another-worker-token', Redis::get("cron:running:{$id}"), '不能删掉别人持有的锁');
    }

    public function test_run_times_out_when_nobody_consumes_the_queue(): void
    {
        $id = $this->insertJob('fixture:cron hello');
        $admin = $this->actingAsAdmin(['system.cron_job.run']);
        $this->overrideConfig('queue.queues.cron-job.consumer', NoopConsumer::class);
        $this->overrideConfig('cron.manual_wait_seconds', 1);

        $started = microtime(true);
        $data = $this->post("/adminapi/system/cron-job/{$id}/run", [], $admin->token)->assertOk()->data();

        $this->assertSame(['status' => 0, 'output' => lang('business.cron_run_submitted')], $data);
        $this->assertGreaterThanOrEqual(0.9, microtime(true) - $started, 'BLPOP 必须真的等满超时');
        $this->assertSame([], $this->logsOf($id));
    }

    public function test_manual_run_ignores_disabled_status(): void
    {
        $id = $this->insertJob('fixture:cron disabled-but-run', ['status' => 0]);
        $admin = $this->actingAsAdmin(['system.cron_job.run']);

        $data = $this->post("/adminapi/system/cron-job/{$id}/run", [], $admin->token)->assertOk()->data();

        $this->assertSame(1, $data['status']);
        $this->assertCount(1, $this->logsOf($id));
    }

    public function test_scheduled_execution_skips_disabled_and_deleted_jobs(): void
    {
        $disabled = $this->insertJob('fixture:cron nope', ['status' => 0]);
        $deleted = $this->insertJob('fixture:cron nope', ['deleted_at' => date('Y-m-d H:i:s')]);
        $service = Container::get(CronJobService::class);

        $service->execute(['cron_job_id' => $disabled, 'trigger' => CronJobService::TRIGGER_SCHEDULED, 'scheduled_at' => date('Y-m-d H:i:00')]);
        $service->execute(['cron_job_id' => $deleted, 'trigger' => CronJobService::TRIGGER_SCHEDULED, 'scheduled_at' => date('Y-m-d H:i:00')]);
        $service->execute(['cron_job_id' => 999999999, 'trigger' => CronJobService::TRIGGER_SCHEDULED]);

        $this->assertSame([], $this->logsOf($disabled));
        $this->assertSame([], $this->logsOf($deleted));
        $this->assertSame(0, (int) Redis::exists("cron:running:{$disabled}"));
    }

    public function test_scheduled_execution_of_an_enabled_job_writes_a_scheduled_log_and_pushes_no_result(): void
    {
        $id = $this->insertJob('fixture:cron scheduled');

        Container::get(CronJobService::class)->execute(['cron_job_id' => $id, 'trigger' => CronJobService::TRIGGER_SCHEDULED, 'scheduled_at' => date('Y-m-d H:i:00')]);

        $logs = $this->logsOf($id);
        $this->assertCount(1, $logs);
        $this->assertSame(CronJobService::TRIGGER_SCHEDULED, (int) $logs[0]->trigger);
        $this->assertSame(1, (int) $logs[0]->status);
        $this->assertSame([], (array) Redis::keys('cron:result:*'), '定时触发没有 token，不回传结果');
    }

    public function test_manual_execution_of_a_vanished_job_still_answers_the_waiter(): void
    {
        $token = bin2hex(random_bytes(16));

        Container::get(CronJobService::class)->execute(['cron_job_id' => 999999999, 'trigger' => CronJobService::TRIGGER_MANUAL, 'token' => $token]);

        $popped = Redis::blpop("cron:result:{$token}", 1);
        $this->assertIsArray($popped, '手动执行时任务已被删除，也要推结果，否则 http 进程白等到超时');
        $this->assertSame(['status' => 0, 'output' => lang('messages.data_not_found')], json_decode((string) $popped[1], true));
    }
}
