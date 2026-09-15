<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\CronJobLogRepository;
use app\repository\system\CronJobRepository;
use support\Db;
use tests\TestCase;

/**
 * 定时任务两张表的仓储（M3 Task 6）。测试库里有一条种子任务（log:archive），
 * 所以断言一律只看本用例插入的 id，不假设表为空。
 */
final class CronJobRepositoryTest extends TestCase
{
    /** @var list<int> */
    private array $jobIds = [];

    protected function tearDown(): void
    {
        if ($this->jobIds !== []) {
            Db::table('cron_job_logs')->whereIn('cron_job_id', $this->jobIds)->delete();
            Db::table('cron_jobs')->whereIn('id', $this->jobIds)->delete();
        }
        $this->jobIds = [];
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function job(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('cron_jobs')->insertGetId(array_merge([
            'name'       => '仓储测试任务' . bin2hex(random_bytes(3)),
            'command'    => 'log:archive --days=90',
            'expression' => '0 3 * * *',
            'status'     => 1,
            'sort'       => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->jobIds[] = $id;

        return $id;
    }

    private function log(int $jobId, string $createdAt, int $status = 1): int
    {
        return (int) Db::table('cron_job_logs')->insertGetId([
            'cron_job_id' => $jobId,
            'trigger'     => 1,
            'status'      => $status,
            'output'      => 'ok',
            'error'       => '',
            'started_at'  => $createdAt,
            'finished_at' => $createdAt,
            'duration'    => 5,
            'created_at'  => $createdAt,
        ]);
    }

    public function test_search_matches_name_or_command_filters_status_and_orders_by_sort_then_id_desc(): void
    {
        $tag = 'repo' . bin2hex(random_bytes(3));
        $a = $this->job(['name' => "甲{$tag}", 'sort' => 2]);
        $b = $this->job(['name' => '乙', 'command' => "log:archive --days=7 --tag={$tag}", 'sort' => 1]);
        $c = $this->job(['name' => "丙{$tag}", 'sort' => 2, 'status' => 0]);
        $repository = new CronJobRepository();

        $all = $repository->getSearchList(['keyword' => $tag], 1, 100);
        $this->assertSame([$b, $c, $a], array_map('intval', array_column($all['list'], 'id')), 'sort asc，同 sort 按 id desc');
        $this->assertSame(['current_page' => 1, 'per_page' => 100, 'total' => 3, 'last_page' => 1], $all['pagination']);

        $enabled = $repository->getSearchList(['keyword' => $tag, 'status' => '1'], 1, 100);
        $this->assertSame([$b, $a], array_map('intval', array_column($enabled['list'], 'id')));

        $disabled = $repository->getSearchList(['keyword' => $tag, 'status' => 0], 1, 100);
        $this->assertSame([$c], array_map('intval', array_column($disabled['list'], 'id')));
    }

    public function test_search_keyword_wildcards_are_escaped(): void
    {
        $this->job(['name' => '通配符探针']);

        $result = (new CronJobRepository())->getSearchList(['keyword' => '%'], 1, 100);

        $this->assertSame(0, $result['pagination']['total'], '% 必须按字面匹配，不能匹配全表');
    }

    public function test_enabled_jobs_excludes_disabled_and_soft_deleted_and_orders_by_sort_then_id(): void
    {
        $late = $this->job(['sort' => 5]);
        $early = $this->job(['sort' => 1]);
        $sameSortLater = $this->job(['sort' => 5]);
        $disabled = $this->job(['status' => 0]);
        $deleted = $this->job(['deleted_at' => date('Y-m-d H:i:s')]);

        $ids = array_map('intval', array_column((new CronJobRepository())->enabledJobs(), 'id'));
        $mine = array_values(array_intersect($ids, $this->jobIds));

        $this->assertSame([$early, $late, $sameSortLater], $mine);
        $this->assertNotContains($disabled, $ids);
        $this->assertNotContains($deleted, $ids);
    }

    public function test_record_run_updates_last_fields_increments_run_count_and_truncates_result(): void
    {
        $id = $this->job();
        $repository = new CronJobRepository();

        $repository->recordRun($id, '2026-09-15 03:00:00', 1, 'first');
        $repository->recordRun($id, '2026-09-16 03:00:00', 0, str_repeat('错', 600));

        $row = Db::table('cron_jobs')->where('id', $id)->first();
        $this->assertSame(2, (int) $row->run_count);
        $this->assertSame('2026-09-16 03:00:00', (string) $row->last_run_at);
        $this->assertSame(0, (int) $row->last_status);
        $this->assertSame(500, mb_strlen((string) $row->last_result));
    }

    public function test_logs_are_listed_per_job_newest_first_with_standard_pagination(): void
    {
        $job = $this->job();
        $other = $this->job();
        $first = $this->log($job, '2026-09-10 03:00:00');
        $second = $this->log($job, '2026-09-11 03:00:00', 0);
        $third = $this->log($job, '2026-09-12 03:00:00');
        $this->log($other, '2026-09-12 03:00:00');
        $repository = new CronJobLogRepository();

        $page1 = $repository->getListByJob($job, 1, 2);
        $this->assertSame([$third, $second], array_map('intval', array_column($page1['list'], 'id')));
        $this->assertSame(['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $page1['pagination']);
        $this->assertSame(['id', 'cron_job_id', 'trigger', 'status', 'output', 'error', 'started_at', 'finished_at', 'duration', 'created_at'], array_keys($page1['list'][0]));
        $this->assertSame(0, $page1['list'][1]['status'], 'status cast 为整数');

        $page2 = $repository->getListByJob($job, 2, 2);
        $this->assertSame([$first], array_map('intval', array_column($page2['list'], 'id')));
    }

    public function test_clear_by_job_with_zero_days_removes_all_logs_of_that_job_only(): void
    {
        $job = $this->job();
        $other = $this->job();
        $this->log($job, date('Y-m-d H:i:s'));
        $this->log($job, '2020-01-01 00:00:00');
        $kept = $this->log($other, '2020-01-01 00:00:00');

        $this->assertSame(2, (new CronJobLogRepository())->clearByJob($job, 0));

        $this->assertSame(0, Db::table('cron_job_logs')->where('cron_job_id', $job)->count());
        $this->assertTrue(Db::table('cron_job_logs')->where('id', $kept)->exists());
    }

    public function test_clear_by_job_keeps_logs_on_or_after_the_cutoff(): void
    {
        $job = $this->job();
        // 截止点 = 今天 0 点往前推 30 天；严格早于它的删除，恰好等于它的保留
        $cutoff = (new \DateTimeImmutable('today'))->modify('-30 days');
        $old = $this->log($job, $cutoff->modify('-1 second')->format('Y-m-d H:i:s'));
        $boundary = $this->log($job, $cutoff->format('Y-m-d H:i:s'));
        $recent = $this->log($job, date('Y-m-d H:i:s'));

        $this->assertSame(1, (new CronJobLogRepository())->clearByJob($job, 30));

        $this->assertFalse(Db::table('cron_job_logs')->where('id', $old)->exists());
        $this->assertTrue(Db::table('cron_job_logs')->where('id', $boundary)->exists());
        $this->assertTrue(Db::table('cron_job_logs')->where('id', $recent)->exists());
    }

    public function test_create_does_not_auto_fill_created_by_because_the_table_is_not_data_scoped(): void
    {
        \core\context\RequestContext::setActingUser(7);
        $row = (new CronJobRepository())->create([
            'name'       => '不自动填创建人',
            'command'    => 'log:archive',
            'expression' => '0 3 * * *',
        ]);
        $this->jobIds[] = (int) $row['id'];

        $this->assertNull(Db::table('cron_jobs')->where('id', $row['id'])->value('created_by'), 'CronJobService::create() 必须显式传 created_by');
    }
}
