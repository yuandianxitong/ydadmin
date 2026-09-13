<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\ColumnDescriptor;
use core\generator\GeneratorRequest;
use core\generator\ModuleBlueprint;
use core\generator\TableDefinition;
use core\generator\TemplateRenderer;
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

    /**
     * 非空 DB_PREFIX 下，生成的 Model::$table 必须是**裸表名**（评审 I4）。
     *
     * Eloquent 的连接层会再给 $table 套一次前缀：模板写物理表名 yd_articles，实际查询就变成
     * yd_yd_articles，该模块每一个接口都报「表不存在」。NameConvention::bareTableName() 从一开始
     * 就写着这条保证、也有自己的单测，只是模板没用它——伞一直在，没人撑。
     *
     * 前缀由 GeneratorService 从 config('database.connections.mysql.prefix') 读出来传进构造函数：
     * core/ 保持纯逻辑、不读配置，所以这里能直接构造出带前缀的蓝图来断言。
     */
    public function test_model_table_property_uses_the_bare_table_name_under_a_non_empty_prefix(): void
    {
        $artifact = $this->articlesBlueprint('yd_')->artifacts()['model'];

        $this->assertSame('articles', $artifact['vars']['bareTableName']);
        $this->assertSame('yd_articles', $artifact['vars']['tableName'], '物理表名仍按原样提供给注释等落点');

        $content = (new TemplateRenderer(base_path('core/generator/stubs')))
            ->render($artifact['stub'], $artifact['vars']);

        $this->assertStringContainsString("protected \$table = 'articles';", $content);
        $this->assertStringNotContainsString("protected \$table = 'yd_articles';", $content, '写物理表名会被双重加前缀');
        // 类注释里的物理表名是对的：那里描述的就是磁盘上那张表
        $this->assertStringContainsString('（yd_articles 表）', $content);
    }

    /** 默认前缀为空：既有调用点与黄金夹具不受影响，裸表名就等于物理表名。 */
    public function test_default_empty_prefix_leaves_the_table_name_untouched(): void
    {
        $vars = $this->articlesBlueprint()->artifacts()['model']['vars'];

        $this->assertSame('yd_articles', $vars['bareTableName']);
        $this->assertSame('yd_articles', $vars['tableName']);
    }

    private function articlesBlueprint(string $tablePrefix = ''): ModuleBlueprint
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

        return new ModuleBlueprint(
            new TableDefinition('yd_articles', '文章', [$column('id', 'PRI'), $column('title')]),
            new GeneratorRequest('yd_articles', 'demo', 'Article', '文章', []),
            $tablePrefix,
        );
    }
}
