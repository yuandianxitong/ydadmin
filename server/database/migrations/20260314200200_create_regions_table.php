<?php

use think\migration\Migrator;

class CreateRegionsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('regions', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '地区表',
        ]);

        $table
            ->addColumn('parent_id', 'integer', ['signed' => false, 'default' => 0, 'comment' => '父级ID'])
            ->addColumn('name', 'string', ['limit' => 50, 'comment' => '名称'])
            ->addColumn('code', 'string', ['limit' => 20, 'default' => '', 'comment' => '编码'])
            ->addColumn('level', 'tinyinteger', ['default' => 1, 'comment' => '层级：1省 2市 3区'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'tinyinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addIndex(['parent_id'])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['level'])
            ->create();
    }

    public function down(): void
    {
        $this->table('regions')->drop()->save();
    }
}
