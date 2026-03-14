<?php

use think\migration\Migrator;

class CreateRoleMenusTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('role_menus', [
            'id' => false,
            'primary_key' => ['role_id', 'menu_id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '角色菜单关联表',
        ]);

        $table
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '角色ID'])
            ->addColumn('menu_id', 'biginteger', ['signed' => false, 'null' => false, 'comment' => '菜单ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['role_id'])
            ->addIndex(['menu_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('role_menus')->drop()->save();
    }
}
