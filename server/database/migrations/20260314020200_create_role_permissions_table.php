<?php

use think\migration\Migrator;

class CreateRolePermissionsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('role_permissions', [
            'id' => false,
            'primary_key' => ['role_id', 'permission_id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '角色权限关联表',
        ]);

        $table
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '角色ID'])
            ->addColumn('permission_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '权限ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['role_id'])
            ->addIndex(['permission_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('role_permissions')->drop()->save();
    }
}
