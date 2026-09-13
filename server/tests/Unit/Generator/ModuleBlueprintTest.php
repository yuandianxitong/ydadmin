<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\ColumnDescriptor;
use core\generator\GeneratorRequest;
use core\generator\ModuleBlueprint;
use core\generator\TableDefinition;
use tests\TestCase;

final class ModuleBlueprintTest extends TestCase
{
    /**
     * 裁定（推翻计划原公式 creatorColumn()||deptColumn()）：只有 dept_id、没有 created_by 的表
     * 不能判成受控。core\base\Repository::$ownerColumn 默认非空的 'created_by'，
     * core\datascope\DataScopeScope 在“仅本人”快照下无条件按它 orWhere；仅有部门列的表若判成
     * 受控，生成的 Repository 会对一个不存在的列拼 SQL，运行时直接报错。黄金夹具表 created_by
     * 与 dept_id 两列都有，覆盖不到这个分支，所以在 vars 层单独钉一个便宜的用例，不必再造
     * 第二张黄金夹具表。
     */
    public function test_tables_with_only_a_department_column_are_not_data_scoped(): void
    {
        $column = static fn (string $name, string $key = ''): ColumnDescriptor => new ColumnDescriptor(
            $name,
            'integer',
            'int',
            true,
            null,
            '',
            $key,
            '',
            'number',
            false,
            true,
            true,
            [],
        );

        $table = new TableDefinition('demo_things', '', [
            $column('id', 'PRI'),
            $column('dept_id'),
        ]);
        $request = new GeneratorRequest('demo_things', 'demo', 'DemoThing', '', []);

        $vars = (new ModuleBlueprint($table, $request))->artifacts()['model']['vars'];

        $this->assertFalse($vars['dataScoped'], '仅有 dept_id、没有 created_by 时不能判定受控');
        $this->assertNull($vars['creatorColumn']);
        $this->assertSame('dept_id', $vars['deptColumn']);
    }

    /** 对照组：有 created_by 就受控，不论有没有 dept_id。 */
    public function test_tables_with_a_creator_column_are_data_scoped(): void
    {
        $column = static fn (string $name, string $key = ''): ColumnDescriptor => new ColumnDescriptor(
            $name,
            'integer',
            'int',
            true,
            null,
            '',
            $key,
            '',
            'number',
            false,
            true,
            true,
            [],
        );

        $table = new TableDefinition('demo_things', '', [
            $column('id', 'PRI'),
            $column('created_by'),
        ]);
        $request = new GeneratorRequest('demo_things', 'demo', 'DemoThing', '', []);

        $vars = (new ModuleBlueprint($table, $request))->artifacts()['model']['vars'];

        $this->assertTrue($vars['dataScoped']);
        $this->assertSame('created_by', $vars['creatorColumn']);
        $this->assertNull($vars['deptColumn']);
    }
}
