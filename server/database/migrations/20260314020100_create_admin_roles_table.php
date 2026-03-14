<?php

use think\migration\Migrator;

class CreateAdminRolesTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('admin_roles', [
            'id' => false,
            'primary_key' => ['admin_id', 'role_id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '管理员角色关联表',
        ]);

        $table
            ->addColumn('admin_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '管理员ID'])
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '角色ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['admin_id'])
            ->addIndex(['role_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('admin_roles')->drop()->save();
    }
}
