<?php

use think\migration\Migrator;

class CreateCronJobLogsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('cron_job_logs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '定时任务日志表',
        ]);

        $table
            ->addColumn('cron_job_id', 'integer', ['signed' => false, 'comment' => '定时任务ID'])
            ->addColumn('status', 'integer', ['limit' => 4, 'comment' => '执行状态'])
            ->addColumn('output', 'text', ['null' => true, 'comment' => '输出内容'])
            ->addColumn('error', 'text', ['null' => true, 'comment' => '错误信息'])
            ->addColumn('started_at', 'datetime', ['null' => true, 'comment' => '开始时间'])
            ->addColumn('finished_at', 'datetime', ['null' => true, 'comment' => '结束时间'])
            ->addColumn('duration', 'integer', ['signed' => false, 'null' => true, 'comment' => '执行时长(秒)'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['cron_job_id'])
            ->addIndex(['created_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('cron_job_logs')->drop()->save();
    }
}
