<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 定时任务管理接口（spec §3、§7）。前端契约：列表 / 详情同时带 expression 与 cron_expression，
 * next_run_at 即时计算（禁用为 null）；新增 / 修改的请求字段叫 cron_expression，入库列叫 expression。
 */
final class CronJobApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/cron-job';

    /** @var list<int> 本用例创建的任务 id（tearDown 先删它们的执行日志，再交给父类删任务行） */
    private array $jobIds = [];

    protected function tearDown(): void
    {
        if ($this->jobIds !== []) {
            Db::table('cron_job_logs')->whereIn('cron_job_id', $this->jobIds)->delete();
        }
        $this->jobIds = [];
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function insertJob(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('cron_jobs')->insertGetId(array_merge([
            'name'       => '测试任务' . bin2hex(random_bytes(3)),
            'command'    => 'log:archive --days=90',
            'expression' => '0 3 * * *',
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

    private function insertLog(int $jobId, string $createdAt, int $status = 1): int
    {
        return (int) Db::table('cron_job_logs')->insertGetId([
            'cron_job_id' => $jobId,
            'trigger'     => 1,
            'status'      => $status,
            'output'      => 'done',
            'error'       => '',
            'started_at'  => $createdAt,
            'finished_at' => $createdAt,
            'duration'    => 12,
            'created_at'  => $createdAt,
        ]);
    }

    /** @param array<string, mixed> $response data */
    private function trackCreated(array $response): int
    {
        $id = (int) ($response['id'] ?? 0);
        $this->assertGreaterThan(0, $id);
        $this->track('cron_jobs', $id);
        $this->jobIds[] = $id;

        return $id;
    }

    public function test_every_endpoint_requires_its_permission(): void
    {
        $id = $this->insertJob();
        $nobody = $this->actingAsAdmin();

        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->get(self::BASE . "/{$id}", [], $nobody->token)->assertCode(403);
        $this->post(self::BASE, ['name' => 'x', 'command' => 'log:archive', 'cron_expression' => '* * * * *'], $nobody->token)->assertCode(403);
        $this->put(self::BASE . "/{$id}", ['name' => 'x'], $nobody->token)->assertCode(403);
        $this->delete(self::BASE . "/{$id}", [], $nobody->token)->assertCode(403);
        $this->put(self::BASE . "/{$id}/status", ['status' => 0], $nobody->token)->assertCode(403);
        $this->get(self::BASE . "/{$id}/logs", [], $nobody->token)->assertCode(403);
        $this->post(self::BASE . "/{$id}/clear-logs", [], $nobody->token)->assertCode(403);
    }

    public function test_list_returns_both_expression_keys_and_next_run_at_only_for_enabled_jobs(): void
    {
        $tag = bin2hex(random_bytes(4));
        $enabled = $this->insertJob(['name' => "启用_{$tag}", 'status' => 1, 'expression' => '0 3 * * *']);
        $disabled = $this->insertJob(['name' => "禁用_{$tag}", 'status' => 0, 'expression' => '*/5 * * * *']);
        $admin = $this->actingAsAdmin(['system.cron_job.list']);

        $data = $this->get(self::BASE, ['keyword' => $tag, 'page' => 1, 'limit' => 10], $admin->token)->assertOk()->data();

        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($data['pagination']));
        $rows = array_column($data['list'], null, 'id');
        $this->assertSame([$enabled, $disabled], array_values(array_intersect([$enabled, $disabled], array_keys($rows))));
        $this->assertSame('0 3 * * *', $rows[$enabled]['expression']);
        $this->assertSame('0 3 * * *', $rows[$enabled]['cron_expression']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} 03:00:00$/', (string) $rows[$enabled]['next_run_at']);
        $this->assertSame('*/5 * * * *', $rows[$disabled]['cron_expression']);
        $this->assertNull($rows[$disabled]['next_run_at']);
    }

    public function test_list_filters_by_status_and_escapes_like_wildcards(): void
    {
        $tag = bin2hex(random_bytes(4));
        $literal = $this->insertJob(['name' => "百分_{$tag}%", 'status' => 1]);
        $this->insertJob(['name' => "百分_{$tag}X", 'status' => 0]);
        $admin = $this->actingAsAdmin(['system.cron_job.list']);

        $ids = array_column($this->get(self::BASE, ['keyword' => "{$tag}%"], $admin->token)->assertOk()->data()['list'], 'id');
        $this->assertSame([$literal], $ids, '% 必须按字面匹配，不能当通配符');

        $ids = array_column($this->get(self::BASE, ['keyword' => $tag, 'status' => 0], $admin->token)->assertOk()->data()['list'], 'id');
        $this->assertNotContains($literal, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_show_returns_the_presented_row_and_404_for_missing(): void
    {
        $id = $this->insertJob(['expression' => '15 * * * *']);
        $admin = $this->actingAsAdmin(['system.cron_job.list']);

        $row = $this->get(self::BASE . "/{$id}", [], $admin->token)->assertOk()->data();
        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('15 * * * *', $row['expression']);
        $this->assertSame('15 * * * *', $row['cron_expression']);
        $this->assertNotNull($row['next_run_at']);

        $this->get(self::BASE . '/999999999', [], $admin->token)->assertCode(404);
    }

    public function test_store_writes_expression_column_and_creator(): void
    {
        $admin = $this->actingAsAdmin(['system.cron_job.create']);

        $data = $this->post(self::BASE, [
            'name'            => '归档日志',
            'command'         => 'log:archive --days=30',
            'cron_expression' => '0 2 * * *',
            'description'     => '每天两点',
            'sort'            => 5,
            'status'          => 0,
        ], $admin->token)->assertOk()->data();
        $id = $this->trackCreated($data);

        $this->assertSame('0 2 * * *', $data['cron_expression']);
        $this->assertNull($data['next_run_at'], '以禁用状态创建');
        $row = Db::table('cron_jobs')->where('id', $id)->first();
        $this->assertSame('0 2 * * *', $row->expression);
        $this->assertSame('log:archive --days=30', $row->command);
        $this->assertSame(5, (int) $row->sort);
        $this->assertSame(0, (int) $row->status);
        $this->assertSame($admin->id, (int) $row->created_by);
    }

    public function test_store_rejects_command_outside_the_whitelist(): void
    {
        $admin = $this->actingAsAdmin(['system.cron_job.create']);

        $response = $this->post(self::BASE, ['name' => '越权', 'command' => 'db:reset --force', 'cron_expression' => '* * * * *'], $admin->token)->assertCode(422);

        $this->assertSame(lang('business.cron_command_not_allowed'), $response->data()['errors']['command']);
        $this->assertSame(0, Db::table('cron_jobs')->where('name', '越权')->count());
    }

    public function test_store_rejects_invalid_expressions_macros_and_six_fields(): void
    {
        $admin = $this->actingAsAdmin(['system.cron_job.create']);

        foreach (['61 * * * *', '@hourly', '0 0 3 * * *', 'every day'] as $expression) {
            $response = $this->post(self::BASE, ['name' => '坏表达式', 'command' => 'log:archive', 'cron_expression' => $expression], $admin->token)->assertCode(422);
            $this->assertSame(lang('validation.cron_expression_invalid'), $response->data()['errors']['cron_expression'], $expression);
        }
        $this->assertSame(0, Db::table('cron_jobs')->where('name', '坏表达式')->count());
    }

    public function test_store_requires_name_command_and_expression(): void
    {
        $admin = $this->actingAsAdmin(['system.cron_job.create']);

        $errors = $this->post(self::BASE, [], $admin->token)->assertCode(422)->data()['errors'];

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('command', $errors);
        $this->assertArrayHasKey('cron_expression', $errors);
    }

    public function test_update_is_partial_and_maps_cron_expression(): void
    {
        $id = $this->insertJob(['name' => '原名', 'command' => 'log:archive --days=90', 'expression' => '0 3 * * *']);
        $admin = $this->actingAsAdmin(['system.cron_job.update']);

        $this->put(self::BASE . "/{$id}", ['cron_expression' => '30 4 * * 1'], $admin->token)->assertOk();

        $row = Db::table('cron_jobs')->where('id', $id)->first();
        $this->assertSame('30 4 * * 1', $row->expression);
        $this->assertSame('原名', $row->name, '未提交的字段保持不变');
        $this->assertSame('log:archive --days=90', $row->command);

        $this->put(self::BASE . "/{$id}", ['command' => 'rm -rf /'], $admin->token)->assertCode(422);
        $this->put(self::BASE . "/{$id}", ['cron_expression' => '@daily'], $admin->token)->assertCode(422);
        $this->put(self::BASE . "/{$id}", ['name' => ''], $admin->token)->assertCode(422);
        $this->put('/adminapi/system/cron-job/999999999', ['name' => '不存在'], $admin->token)->assertCode(404);
        $this->assertSame('log:archive --days=90', Db::table('cron_jobs')->where('id', $id)->value('command'));
    }

    public function test_delete_soft_deletes_and_keeps_logs(): void
    {
        $id = $this->insertJob();
        $this->insertLog($id, date('Y-m-d H:i:s'));
        $admin = $this->actingAsAdmin(['system.cron_job.delete', 'system.cron_job.list']);

        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();

        $this->assertNotNull(Db::table('cron_jobs')->where('id', $id)->value('deleted_at'));
        $this->assertSame(1, Db::table('cron_job_logs')->where('cron_job_id', $id)->count(), '软删不删执行日志');
        $this->get(self::BASE . "/{$id}", [], $admin->token)->assertCode(404);
        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertCode(404);
    }

    public function test_status_toggles_and_validates(): void
    {
        $id = $this->insertJob(['status' => 1]);
        $admin = $this->actingAsAdmin(['system.cron_job.update']);

        $this->put(self::BASE . "/{$id}/status", ['status' => 0], $admin->token)->assertOk();
        $this->assertSame(0, (int) Db::table('cron_jobs')->where('id', $id)->value('status'));

        $this->put(self::BASE . "/{$id}/status", ['status' => 2], $admin->token)->assertCode(422);
        $this->put(self::BASE . "/{$id}/status", [], $admin->token)->assertCode(422);
        $this->put(self::BASE . '/999999999/status', ['status' => 1], $admin->token)->assertCode(404);
    }

    public function test_logs_are_paginated_newest_first(): void
    {
        $id = $this->insertJob();
        $first = $this->insertLog($id, date('Y-m-d H:i:s', time() - 120));
        $second = $this->insertLog($id, date('Y-m-d H:i:s', time() - 60), 0);
        $third = $this->insertLog($id, date('Y-m-d H:i:s'));
        $admin = $this->actingAsAdmin(['system.cron_job.list']);

        $data = $this->get(self::BASE . "/{$id}/logs", ['page' => 1, 'limit' => 2], $admin->token)->assertOk()->data();

        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $data['pagination']);
        $this->assertSame([$third, $second], array_map('intval', array_column($data['list'], 'id')));
        foreach (['status', 'output', 'error', 'duration', 'started_at'] as $field) {
            $this->assertArrayHasKey($field, $data['list'][0], "前端日志弹窗读 {$field}");
        }
        $this->assertNotContains($first, array_column($data['list'], 'id'));

        $this->get(self::BASE . '/999999999/logs', [], $admin->token)->assertCode(404);
    }

    public function test_clear_logs_defaults_to_keeping_30_days_and_zero_clears_all(): void
    {
        $id = $this->insertJob();
        $other = $this->insertJob();
        $this->insertLog($id, date('Y-m-d H:i:s', strtotime('-40 days')));
        $this->insertLog($id, date('Y-m-d H:i:s', strtotime('-1 day')));
        $this->insertLog($other, date('Y-m-d H:i:s', strtotime('-40 days')));
        $admin = $this->actingAsAdmin(['system.cron_job.clear']);

        $data = $this->post(self::BASE . "/{$id}/clear-logs", [], $admin->token)->assertOk()->data();
        $this->assertSame(['count' => 1], $data);
        $this->assertSame(1, Db::table('cron_job_logs')->where('cron_job_id', $id)->count());

        $data = $this->post(self::BASE . "/{$id}/clear-logs", ['keep_days' => 0], $admin->token)->assertOk()->data();
        $this->assertSame(['count' => 1], $data);
        $this->assertSame(0, Db::table('cron_job_logs')->where('cron_job_id', $id)->count());
        $this->assertSame(1, Db::table('cron_job_logs')->where('cron_job_id', $other)->count(), '只清本任务的日志');

        $this->post(self::BASE . "/{$id}/clear-logs", ['keep_days' => -1], $admin->token)->assertCode(422);
        $this->post(self::BASE . "/{$id}/clear-logs", ['keep_days' => 3651], $admin->token)->assertCode(422);
        $this->post(self::BASE . '/999999999/clear-logs', [], $admin->token)->assertCode(404);
    }
}
