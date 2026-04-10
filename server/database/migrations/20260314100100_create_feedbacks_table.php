<?php

use think\migration\Migrator;

class CreateFeedbacksTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('feedbacks', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '用户反馈表',
        ]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('type', 'string', ['limit' => 30, 'default' => 'suggestion', 'comment' => '类型：suggestion/bug/complaint/other'])
            ->addColumn('content', 'text', ['comment' => '反馈内容'])
            ->addColumn('images', 'text', ['null' => true, 'comment' => '图片路径JSON数组'])
            ->addColumn('contact', 'string', ['limit' => 100, 'null' => true, 'comment' => '联系方式'])
            ->addColumn('status', 'tinyinteger', ['default' => 0, 'comment' => '状态：0待处理 1处理中 2已回复 3已关闭'])
            ->addColumn('reply', 'text', ['null' => true, 'comment' => '管理员回复'])
            ->addColumn('replied_at', 'datetime', ['null' => true, 'comment' => '回复时间'])
            ->addColumn('replied_by', 'integer', ['null' => true, 'signed' => false, 'comment' => '回复人ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['user_id'])
            ->addIndex(['status'])
            ->addIndex(['type'])
            ->create();
    }

    public function down(): void
    {
        $this->table('feedbacks')->drop()->save();
    }
}
