<?php

use think\migration\Migrator;

class CreateNotificationReadsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('notification_reads', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '通知已读记录表',
        ]);

        $table
            ->addColumn('notification_id', 'integer', ['signed' => false, 'comment' => '通知ID'])
            ->addColumn('admin_id', 'integer', ['signed' => false, 'comment' => '管理员ID'])
            ->addColumn('read_at', 'datetime', ['null' => true, 'comment' => '阅读时间'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['notification_id', 'admin_id'], ['unique' => true, 'name' => 'idx_notification_admin_unique'])
            ->addIndex(['admin_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('notification_reads')->drop()->save();
    }
}
