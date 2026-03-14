<?php

use think\migration\Migrator;

class CreateAnnouncementsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('announcements', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '公告表',
        ]);

        $table
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '标题'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('type', 'tinyinteger', ['default' => 1, 'comment' => '类型：1通知 2更新 3活动'])
            ->addColumn('status', 'tinyinteger', ['default' => 0, 'comment' => '状态：0草稿 1已发布'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('publish_at', 'datetime', ['null' => true, 'comment' => '发布时间'])
            ->addColumn('admin_id', 'integer', ['signed' => false, 'comment' => '管理员ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['status'])
            ->addIndex(['type'])
            ->addIndex(['sort'])
            ->create();
    }

    public function down(): void
    {
        $this->table('announcements')->drop()->save();
    }
}
