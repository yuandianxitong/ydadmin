<?php

use think\migration\Migrator;

class CreateNotificationsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('notifications', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '通知表',
        ]);

        $table
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '通知标题'])
            ->addColumn('content', 'text', ['null' => true, 'comment' => '通知内容'])
            ->addColumn('type', 'integer', ['limit' => 4, 'default' => 1, 'comment' => '类型:1系统,2待办,3业务'])
            ->addColumn('sender_id', 'integer', ['signed' => false, 'null' => true, 'comment' => '发送人ID'])
            ->addColumn('target_type', 'integer', ['limit' => 4, 'default' => 1, 'comment' => '目标类型:1全部,2指定'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['type'])
            ->addIndex(['sender_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('notifications')->drop()->save();
    }
}
