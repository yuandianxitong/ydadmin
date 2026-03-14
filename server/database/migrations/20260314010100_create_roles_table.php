<?php

use think\migration\Migrator;

class CreateRolesTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('roles', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '角色表',
        ]);

        $table
            ->addColumn('name', 'string', ['limit' => 50, 'comment' => '角色标识'])
            ->addColumn('title', 'string', ['limit' => 100, 'comment' => '角色名称'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '角色描述'])
            ->addColumn('data_scope', 'integer', ['limit' => 4, 'default' => 1, 'comment' => '数据范围:1全部,2自定义,3本部门,4部门及以下,5仅自己'])
            ->addColumn('is_system', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '是否系统角色'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_by', 'biginteger', ['null' => true, 'comment' => '创建人'])
            ->addColumn('updated_by', 'biginteger', ['null' => true, 'comment' => '更新人'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['sort'])
            ->create();
    }

    public function down(): void
    {
        $this->table('roles')->drop()->save();
    }
}
