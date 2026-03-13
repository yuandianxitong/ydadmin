<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\database;

use think\migration\Migrator;
use think\migration\db\Table;

abstract class Migration extends Migrator
{
    /**
     * 创建标准表结构
     */
    protected function createStandardTable(string $tableName, array $options = []): Table
    {
        $defaultOptions = [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => $options['comment'] ?? ''
        ];

        $options = array_merge($defaultOptions, $options);
        $table = $this->table($tableName, $options);

        // 添加标准字段
        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'signed' => false,
            'comment' => '主键ID'
        ]);

        return $table;
    }

    /**
     * 添加时间戳字段
     */
    protected function addTimestamps(Table $table): Table
    {
        return $table->addTimestamps('created_at', 'updated_at');
    }

    /**
     * 添加软删除字段
     */
    protected function addSoftDelete(Table $table): Table
    {
        return $table->addColumn('deleted_at', 'timestamp', [
            'null' => true,
            'comment' => '删除时间'
        ]);
    }

    /**
     * 添加状态字段
     */
    protected function addStatus(Table $table, int $default = 1): Table
    {
        return $table->addColumn('status', 'boolean', [
            'default' => $default,
            'comment' => '状态:1正常,0禁用'
        ]);
    }

    /**
     * 添加排序字段
     */
    protected function addSort(Table $table, int $default = 0): Table
    {
        return $table->addColumn('sort', 'integer', [
            'default' => $default,
            'comment' => '排序权重'
        ]);
    }

    /**
     * 添加创建者字段
     */
    protected function addCreatedBy(Table $table): Table
    {
        return $table->addColumn('created_by', 'biginteger', [
            'signed' => false,
            'null' => true,
            'comment' => '创建者ID'
        ]);
    }

    /**
     * 添加更新者字段
     */
    protected function addUpdatedBy(Table $table): Table
    {
        return $table->addColumn('updated_by', 'biginteger', [
            'signed' => false,
            'null' => true,
            'comment' => '更新者ID'
        ]);
    }
}
