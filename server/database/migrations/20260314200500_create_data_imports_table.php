<?php

use think\migration\Migrator;

class CreateDataImportsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('data_imports', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '数据导入表',
        ]);

        $table
            ->addColumn('module', 'string', ['limit' => 50, 'comment' => '模块'])
            ->addColumn('filename', 'string', ['limit' => 200, 'comment' => '文件名'])
            ->addColumn('total_count', 'integer', ['default' => 0, 'comment' => '总条数'])
            ->addColumn('success_count', 'integer', ['default' => 0, 'comment' => '成功条数'])
            ->addColumn('fail_count', 'integer', ['default' => 0, 'comment' => '失败条数'])
            ->addColumn('status', 'tinyinteger', ['default' => 0, 'comment' => '状态：0处理中 1完成 2失败'])
            ->addColumn('errors', 'text', ['null' => true, 'comment' => '错误信息JSON'])
            ->addColumn('admin_id', 'integer', ['signed' => false, 'comment' => '管理员ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['module'])
            ->addIndex(['admin_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('data_imports')->drop()->save();
    }
}
