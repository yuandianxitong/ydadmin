<?php

use think\migration\Migrator;

class CreatePermissionsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('permissions', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '权限表',
        ]);

        $table
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '权限标识'])
            ->addColumn('title', 'string', ['limit' => 100, 'comment' => '权限名称'])
            ->addColumn('group', 'string', ['limit' => 50, 'null' => true, 'comment' => '权限分组'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '权限描述'])
            ->addColumn('guard_name', 'string', ['limit' => 50, 'default' => 'admin', 'comment' => '守卫名称'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['group'])
            ->addIndex(['guard_name'])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('permissions')->drop()->save();
    }
}
