<?php
declare(strict_types=1);

use think\migration\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->table('admins')->insert([
            [
                'username'      => 'admin',
                'email'         => 'admin@dev007.com',
                'password'      => password_hash('admin123456', PASSWORD_DEFAULT),
                'nickname'      => '超级管理员',
                'department_id' => 1,
                'status'        => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ])->saveData();

        $this->table('admin_roles')->insert([
            [
                'admin_id'   => 1,
                'role_id'    => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ])->saveData();
    }
}
