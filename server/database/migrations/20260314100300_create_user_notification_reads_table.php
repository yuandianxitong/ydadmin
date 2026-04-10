<?php

use think\migration\Migrator;

class CreateUserNotificationReadsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('user_notification_reads', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '用户通知已读记录表',
        ]);

        $table
            ->addColumn('notification_id', 'integer', ['signed' => false, 'comment' => '通知ID'])
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('read_at', 'datetime', ['null' => true, 'comment' => '阅读时间'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['notification_id', 'user_id'], ['unique' => true, 'name' => 'idx_notification_user_unique'])
            ->addIndex(['user_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('user_notification_reads')->drop()->save();
    }
}
