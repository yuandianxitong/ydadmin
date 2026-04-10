<?php

use think\migration\Migrator;

class CreateMenusTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('menus', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '菜单表',
        ]);

        $table
            ->addColumn('parent_id', 'biginteger', ['default' => 0, 'comment' => '父级ID'])
            ->addColumn('type', 'integer', ['limit' => 4, 'comment' => '类型:1目录,2菜单,3按钮'])
            ->addColumn('title', 'string', ['limit' => 100, 'comment' => '菜单标题'])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => true, 'comment' => '路由名称'])
            ->addColumn('path', 'string', ['limit' => 200, 'null' => true, 'comment' => '路由路径'])
            ->addColumn('component', 'string', ['limit' => 255, 'null' => true, 'comment' => '组件路径'])
            ->addColumn('redirect', 'string', ['limit' => 200, 'null' => true, 'comment' => '重定向地址'])
            ->addColumn('icon', 'string', ['limit' => 100, 'null' => true, 'comment' => '图标'])
            ->addColumn('permission', 'string', ['limit' => 100, 'null' => true, 'comment' => '权限标识'])
            ->addColumn('is_hidden', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '是否隐藏:0否,1是'])
            ->addColumn('is_cache', 'integer', ['limit' => 4, 'default' => 1, 'comment' => '是否缓存:0否,1是'])
            ->addColumn('is_affix', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '是否固定:0否,1是'])
            ->addColumn('is_iframe', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '是否内嵌:0否,1是'])
            ->addColumn('external_link', 'string', ['limit' => 255, 'null' => true, 'comment' => '外链地址'])
            ->addColumn('breadcrumb', 'integer', ['limit' => 4, 'default' => 1, 'comment' => '是否显示面包屑:0否,1是'])
            ->addColumn('active_menu', 'string', ['limit' => 200, 'null' => true, 'comment' => '高亮菜单'])
            ->addColumn('meta', 'json', ['null' => true, 'comment' => '元数据'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_by', 'biginteger', ['null' => true, 'comment' => '创建人'])
            ->addColumn('updated_by', 'biginteger', ['null' => true, 'comment' => '更新人'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['parent_id'])
            ->addIndex(['type'])
            ->addIndex(['name'])
            ->addIndex(['path'])
            ->addIndex(['permission'])
            ->addIndex(['status'])
            ->addIndex(['sort'])
            ->create();
    }

    public function down(): void
    {
        $this->table('menus')->drop()->save();
    }
}
