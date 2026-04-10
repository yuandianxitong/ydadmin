<?php

use think\migration\Migrator;

class CreateDepartmentsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('departments', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '部门表',
        ]);

        $table
            ->addColumn('parent_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '父级ID'])
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '部门名称'])
            ->addColumn('code', 'string', ['limit' => 50, 'null' => true, 'comment' => '部门编码'])
            ->addColumn('leader', 'string', ['limit' => 50, 'null' => true, 'comment' => '负责人'])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'comment' => '联系电话'])
            ->addColumn('email', 'string', ['limit' => 100, 'null' => true, 'comment' => '邮箱'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '备注'])
            ->addColumn('created_by', 'integer', ['limit' => 10, 'null' => true, 'comment' => '创建人'])
            ->addColumn('updated_by', 'integer', ['limit' => 10, 'null' => true, 'comment' => '更新人'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['parent_id'])
            ->addIndex(['status'])
            ->addIndex(['code'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('departments')->drop()->save();
    }
}
