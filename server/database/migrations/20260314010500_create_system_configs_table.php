<?php

use think\migration\Migrator;

class CreateSystemConfigsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('system_configs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '系统配置表',
        ]);

        $table
            ->addColumn('config_key', 'string', ['limit' => 100, 'comment' => '配置键'])
            ->addColumn('config_value', 'text', ['null' => true, 'comment' => '配置值'])
            ->addColumn('config_group', 'string', ['limit' => 50, 'default' => 'basic', 'comment' => '配置分组'])
            ->addColumn('config_type', 'string', ['limit' => 20, 'default' => 'string', 'comment' => '配置类型'])
            ->addColumn('config_name', 'string', ['limit' => 100, 'null' => true, 'comment' => '配置名称'])
            ->addColumn('config_desc', 'string', ['limit' => 255, 'null' => true, 'comment' => '配置描述'])
            ->addColumn('config_options', 'json', ['null' => true, 'comment' => '配置选项'])
            ->addColumn('config_depends', 'json', ['null' => true, 'comment' => '配置依赖'])
            ->addColumn('sort_order', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['config_key'], ['unique' => true])
            ->addIndex(['config_group'])
            ->addIndex(['status'])
            ->addIndex(['sort_order'])
            ->create();
    }

    public function down(): void
    {
        $this->table('system_configs')->drop()->save();
    }
}
