<?php

use think\migration\Migrator;

class CreateUsersTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('users', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '用户表',
        ]);

        $table
            ->addColumn('nickname', 'string', ['limit' => 50, 'null' => true, 'comment' => '昵称'])
            ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true, 'comment' => '头像'])
            ->addColumn('mobile', 'string', ['limit' => 20, 'null' => true, 'comment' => '手机号'])
            ->addColumn('email', 'string', ['limit' => 100, 'null' => true, 'comment' => '邮箱'])
            ->addColumn('password', 'string', ['limit' => 255, 'comment' => '密码'])
            ->addColumn('gender', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '性别:0未知,1男,2女'])
            ->addColumn('birthday', 'date', ['null' => true, 'comment' => '生日'])
            ->addColumn('openid', 'string', ['limit' => 128, 'null' => true, 'comment' => '公众号OpenID'])
            ->addColumn('unionid', 'string', ['limit' => 128, 'null' => true, 'comment' => 'UnionID'])
            ->addColumn('mini_openid', 'string', ['limit' => 128, 'null' => true, 'comment' => '小程序OpenID'])
            ->addColumn('last_login_ip', 'string', ['limit' => 45, 'null' => true, 'comment' => '最后登录IP'])
            ->addColumn('last_login_time', 'datetime', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('login_count', 'integer', ['default' => 0, 'comment' => '登录次数'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['mobile'], ['unique' => true])
            ->addIndex(['openid'])
            ->addIndex(['unionid'])
            ->addIndex(['mini_openid'])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('users')->drop()->save();
    }
}
