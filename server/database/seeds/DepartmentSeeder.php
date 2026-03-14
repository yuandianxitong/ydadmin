<?php
declare(strict_types=1);

use think\migration\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->table('departments')->insert([
            ['id' => 1, 'parent_id' => 0, 'name' => '总公司',  'code' => 'HQ',        'leader' => '管理员', 'sort' => 0, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'parent_id' => 1, 'name' => '技术部',  'code' => 'TECH',      'leader' => null,     'sort' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'parent_id' => 1, 'name' => '市场部',  'code' => 'MARKET',    'leader' => null,     'sort' => 2, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'parent_id' => 1, 'name' => '财务部',  'code' => 'FINANCE',   'leader' => null,     'sort' => 3, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'parent_id' => 2, 'name' => '前端组',  'code' => 'TECH-FE',   'leader' => null,     'sort' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'parent_id' => 2, 'name' => '后端组',  'code' => 'TECH-BE',   'leader' => null,     'sort' => 2, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ])->saveData();
    }
}
