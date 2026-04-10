<?php

use think\migration\Migrator;

class CreateFilesTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('files', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '文件表',
        ]);

        $table
            ->addColumn('name', 'string', ['limit' => 255, 'comment' => '文件名称'])
            ->addColumn('path', 'string', ['limit' => 500, 'comment' => '文件路径'])
            ->addColumn('url', 'string', ['limit' => 500, 'null' => true, 'comment' => '文件URL'])
            ->addColumn('mime_type', 'string', ['limit' => 100, 'null' => true, 'comment' => 'MIME类型'])
            ->addColumn('extension', 'string', ['limit' => 20, 'null' => true, 'comment' => '文件扩展名'])
            ->addColumn('size', 'biginteger', ['signed' => false, 'comment' => '文件大小(字节)'])
            ->addColumn('group', 'string', ['limit' => 100, 'default' => '默认', 'comment' => '文件分组'])
            ->addColumn('upload_by', 'integer', ['signed' => false, 'null' => true, 'comment' => '上传人'])
            ->addColumn('storage', 'string', ['limit' => 50, 'default' => 'local', 'comment' => '存储方式'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['group'])
            ->addIndex(['mime_type'])
            ->addIndex(['upload_by'])
            ->addIndex(['created_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('files')->drop()->save();
    }
}
