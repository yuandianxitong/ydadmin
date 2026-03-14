<?php

use think\migration\Migrator;

class CreateUserNotificationsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('user_notifications', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '用户站内通知表',
        ]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'default' => 0, 'comment' => '用户ID，0为全体'])
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '标题'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('type', 'string', ['limit' => 30, 'default' => 'system', 'comment' => '类型：system/order/payment/feedback'])
            ->addColumn('biz_id', 'integer', ['null' => true, 'comment' => '关联业务ID'])
            ->addColumn('extra', 'text', ['null' => true, 'comment' => '额外数据JSON'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['user_id'])
            ->addIndex(['type'])
            ->create();
    }

    public function down(): void
    {
        $this->table('user_notifications')->drop()->save();
    }
}
