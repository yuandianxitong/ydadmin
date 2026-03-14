<?php

use think\migration\Migrator;

class CreateWechatAutoRepliesTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('wechat_auto_replies', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '微信自动回复表',
        ]);

        $table
            ->addColumn('type', 'string', ['limit' => 20, 'comment' => '回复类型'])
            ->addColumn('keyword', 'string', ['limit' => 200, 'null' => true, 'comment' => '关键词'])
            ->addColumn('match_type', 'string', ['limit' => 10, 'default' => 'exact', 'comment' => '匹配方式'])
            ->addColumn('reply_type', 'string', ['limit' => 10, 'default' => 'text', 'comment' => '回复内容类型'])
            ->addColumn('content', 'text', ['null' => true, 'comment' => '回复内容'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort_order', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['type'])
            ->addIndex(['keyword'])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('wechat_auto_replies')->drop()->save();
    }
}
