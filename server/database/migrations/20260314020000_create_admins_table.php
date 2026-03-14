<?php

use think\migration\Migrator;

class CreateAdminsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('admins', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '管理员表',
        ]);

        $table
            ->addColumn('username', 'string', ['limit' => 50, 'comment' => '用户名'])
            ->addColumn('email', 'string', ['limit' => 100, 'comment' => '邮箱'])
            ->addColumn('mobile', 'string', ['limit' => 20, 'null' => true, 'comment' => '手机号'])
            ->addColumn('password', 'string', ['limit' => 255, 'comment' => '密码'])
            ->addColumn('nickname', 'string', ['limit' => 50, 'null' => true, 'comment' => '昵称'])
            ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true, 'comment' => '头像'])
            ->addColumn('department_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '部门ID'])
            ->addColumn('department', 'string', ['limit' => 100, 'null' => true, 'comment' => '部门名称'])
            ->addColumn('position', 'string', ['limit' => 100, 'null' => true, 'comment' => '职位'])
            ->addColumn('last_login_ip', 'string', ['limit' => 45, 'null' => true, 'comment' => '最后登录IP'])
            ->addColumn('last_login_time', 'datetime', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('login_count', 'integer', ['default' => 0, 'comment' => '登录次数'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('created_by', 'biginteger', ['null' => true, 'comment' => '创建人'])
            ->addColumn('updated_by', 'biginteger', ['null' => true, 'comment' => '更新人'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['username'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['mobile'])
            ->addIndex(['status'])
            ->addIndex(['created_at'])
            ->addIndex(['department_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('admins')->drop()->save();
    }
}
