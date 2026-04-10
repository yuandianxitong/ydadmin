<?php

use think\migration\Migrator;

class CreateDictionaryItemsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('dictionary_items', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '字典项表',
        ]);

        $table
            ->addColumn('dictionary_id', 'integer', ['signed' => false, 'comment' => '字典ID'])
            ->addColumn('label', 'string', ['limit' => 100, 'comment' => '标签'])
            ->addColumn('value', 'string', ['limit' => 100, 'comment' => '值'])
            ->addColumn('tag_type', 'string', ['limit' => 50, 'null' => true, 'comment' => '标签类型'])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true, 'comment' => '描述'])
            ->addColumn('status', 'integer', ['limit' => 4, 'default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['dictionary_id'])
            ->addIndex(['status'])
            ->addIndex(['dictionary_id', 'value'], ['unique' => true, 'name' => 'idx_dictionary_value_unique'])
            ->create();
    }

    public function down(): void
    {
        $this->table('dictionary_items')->drop()->save();
    }
}
