<?php

use think\migration\Migrator;

class CreateMessageLogsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('message_logs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '消息发送日志表',
        ]);

        $table
            ->addColumn('template_id', 'biginteger', ['signed' => false, 'null' => true, 'comment' => '模板ID'])
            ->addColumn('template_code', 'string', ['limit' => 50, 'null' => true, 'comment' => '模板编码'])
            ->addColumn('channel', 'string', ['limit' => 20, 'comment' => '发送渠道'])
            ->addColumn('receiver', 'string', ['limit' => 200, 'comment' => '接收者'])
            ->addColumn('content', 'text', ['null' => true, 'comment' => '发送内容'])
            ->addColumn('variables', 'json', ['null' => true, 'comment' => '模板变量'])
            ->addColumn('status', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '状态:0待发,1成功,2失败'])
            ->addColumn('error_msg', 'string', ['limit' => 500, 'null' => true, 'comment' => '错误信息'])
            ->addColumn('sent_at', 'datetime', ['null' => true, 'comment' => '发送时间'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['template_id'])
            ->addIndex(['channel'])
            ->addIndex(['status'])
            ->addIndex(['created_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('message_logs')->drop()->save();
    }
}
