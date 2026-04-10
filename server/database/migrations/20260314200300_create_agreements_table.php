<?php

use think\migration\Migrator;

class CreateAgreementsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('agreements', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '协议表',
        ]);

        $table
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '标题'])
            ->addColumn('code', 'string', ['limit' => 50, 'comment' => '协议编码'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('status', 'tinyinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('agreements')->drop()->save();
    }
}
