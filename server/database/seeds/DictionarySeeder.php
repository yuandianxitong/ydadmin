<?php
declare(strict_types=1);

use think\migration\Seeder;

class DictionarySeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->table('dictionaries')->insert([
            ['id' => 1, 'name' => '性别', 'code' => 'gender',        'description' => '用户性别',           'status' => 1, 'sort' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '状态', 'code' => 'common_status', 'description' => '通用启用/禁用状态', 'status' => 1, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
        ])->saveData();

        $this->table('dictionary_items')->insert([
            ['dictionary_id' => 1, 'label' => '男',   'value' => '1', 'tag_type' => null,       'status' => 1, 'sort' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['dictionary_id' => 1, 'label' => '女',   'value' => '2', 'tag_type' => null,       'status' => 1, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['dictionary_id' => 1, 'label' => '未知', 'value' => '0', 'tag_type' => 'info',   'status' => 1, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['dictionary_id' => 2, 'label' => '启用', 'value' => '1', 'tag_type' => 'success','status' => 1, 'sort' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['dictionary_id' => 2, 'label' => '禁用', 'value' => '0', 'tag_type' => 'danger', 'status' => 1, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
        ])->saveData();
    }
}
