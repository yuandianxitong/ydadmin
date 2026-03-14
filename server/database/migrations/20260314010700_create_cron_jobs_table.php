<?php

use think\migration\Migrator;

class CreateCronJobsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('cron_jobs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '定时任务表',
        ]);

        $table
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '任务名称'])
            ->addColumn('command', 'string', ['limit' => 255, 'comment' => '执行命令'])
            ->addColumn('expression', 'string', ['limit' => 100, 'comment' => 'Cron表达式'])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'comment' => '任务描述'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('last_run_at', 'datetime', ['null' => true, 'comment' => '上次运行时间'])
            ->addColumn('last_result', 'text', ['null' => true, 'comment' => '上次运行结果'])
            ->addColumn('last_status', 'integer', ['limit' => 4, 'null' => true, 'comment' => '上次运行状态'])
            ->addColumn('run_count', 'integer', ['signed' => false, 'default' => 0, 'comment' => '运行次数'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_by', 'integer', ['signed' => false, 'null' => true, 'comment' => '创建人'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('cron_jobs')->drop()->save();
    }
}
