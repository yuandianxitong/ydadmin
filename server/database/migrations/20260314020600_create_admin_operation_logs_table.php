<?php

use think\migration\Migrator;

class CreateAdminOperationLogsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('admin_operation_logs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '管理员操作日志表',
        ]);

        $table
            ->addColumn('admin_id', 'biginteger', ['comment' => '管理员ID'])
            ->addColumn('username', 'string', ['limit' => 50, 'null' => true, 'comment' => '用户名'])
            ->addColumn('method', 'string', ['limit' => 10, 'comment' => '请求方法'])
            ->addColumn('path', 'string', ['limit' => 255, 'comment' => '请求路径'])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true, 'comment' => '请求IP'])
            ->addColumn('user_agent', 'text', ['null' => true, 'comment' => 'User Agent'])
            ->addColumn('action', 'string', ['limit' => 100, 'null' => true, 'comment' => '操作动作'])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'comment' => '操作描述'])
            ->addColumn('params', 'json', ['null' => true, 'comment' => '请求参数'])
            ->addColumn('result', 'json', ['null' => true, 'comment' => '响应结果'])
            ->addColumn('operation_time', 'datetime', ['null' => true, 'comment' => '操作时间'])
            ->addColumn('execution_time', 'decimal', ['precision' => 8, 'scale' => 3, 'null' => true, 'comment' => '执行时间(秒)'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['admin_id'])
            ->addIndex(['username'])
            ->addIndex(['method'])
            ->addIndex(['path'])
            ->addIndex(['action'])
            ->addIndex(['operation_time'])
            ->create();
    }

    public function down(): void
    {
        $this->table('admin_operation_logs')->drop()->save();
    }
}
