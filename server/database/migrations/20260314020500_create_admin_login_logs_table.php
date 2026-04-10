<?php

use think\migration\Migrator;

class CreateAdminLoginLogsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('admin_login_logs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '管理员登录日志表',
        ]);

        $table
            ->addColumn('admin_id', 'biginteger', ['comment' => '管理员ID'])
            ->addColumn('username', 'string', ['limit' => 50, 'comment' => '用户名'])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true, 'comment' => '登录IP'])
            ->addColumn('user_agent', 'text', ['null' => true, 'comment' => 'User Agent'])
            ->addColumn('login_time', 'datetime', ['comment' => '登录时间'])
            ->addColumn('login_result', 'integer', ['limit' => 4, 'comment' => '登录结果:1成功,0失败'])
            ->addColumn('login_message', 'string', ['limit' => 255, 'null' => true, 'comment' => '登录消息'])
            ->addColumn('browser', 'string', ['limit' => 100, 'null' => true, 'comment' => '浏览器'])
            ->addColumn('os', 'string', ['limit' => 100, 'null' => true, 'comment' => '操作系统'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['admin_id'])
            ->addIndex(['username'])
            ->addIndex(['ip'])
            ->addIndex(['login_time'])
            ->addIndex(['login_result'])
            ->create();
    }

    public function down(): void
    {
        $this->table('admin_login_logs')->drop()->save();
    }
}
