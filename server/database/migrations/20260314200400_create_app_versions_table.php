<?php

use think\migration\Migrator;

class CreateAppVersionsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('app_versions', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'APP版本表',
        ]);

        $table
            ->addColumn('platform', 'string', ['limit' => 20, 'comment' => '平台'])
            ->addColumn('version', 'string', ['limit' => 20, 'comment' => '版本号'])
            ->addColumn('version_code', 'integer', ['signed' => false, 'comment' => '版本编码'])
            ->addColumn('download_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '下载地址'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '版本描述'])
            ->addColumn('force_update', 'tinyinteger', ['default' => 0, 'comment' => '强制更新：0否 1是'])
            ->addColumn('status', 'tinyinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['platform', 'version_code'])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('app_versions')->drop()->save();
    }
}
