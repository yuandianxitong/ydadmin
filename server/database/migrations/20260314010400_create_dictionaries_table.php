<?php

use think\migration\Migrator;

class CreateDictionariesTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('dictionaries', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '字典表',
        ]);

        $table
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '字典名称'])
            ->addColumn('code', 'string', ['limit' => 100, 'comment' => '字典编码'])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true, 'comment' => '描述'])
            ->addColumn('status', 'integer', ['limit' => 4, 'default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('dictionaries')->drop()->save();
    }
}
