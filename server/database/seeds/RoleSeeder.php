<?php
declare(strict_types=1);

use think\migration\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->table('roles')->insert([
            [
                'id'          => 1,
                'name'        => 'super_admin',
                'title'       => '超级管理员',
                'description' => '系统超级管理员，拥有所有权限',
                'data_scope'  => 1,
                'is_system'   => 1,
                'status'      => 1,
                'sort'        => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ])->saveData();
    }
}
