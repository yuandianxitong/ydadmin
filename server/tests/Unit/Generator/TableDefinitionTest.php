<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\ColumnDescriptor;
use core\generator\TableDefinition;
use tests\TestCase;

final class TableDefinitionTest extends TestCase
{
    private function column(string $name, string $type = 'string', string $key = '', string $extra = ''): ColumnDescriptor
    {
        return new ColumnDescriptor(
            name: $name,
            type: $type,
            rawType: $type,
            nullable: false,
            default: null,
            comment: '',
            key: $key,
            extra: $extra,
            formType: 'input',
            searchable: false,
            inList: true,
            inForm: true,
            enumValues: [],
        );
    }

    /** 照黄金夹具表 gen_articles 的关键列（不需要全部 17 列，够覆盖每个方法分支即可）。 */
    private function fixtureTable(): TableDefinition
    {
        return new TableDefinition('gen_articles', '生成器夹具表', [
            $this->column('id', 'integer', 'PRI', 'auto_increment'),
            $this->column('title'),
            $this->column('slug', 'string', 'UNI'),
            $this->column('status', 'integer', 'MUL'),
            $this->column('created_by', 'integer'),
            $this->column('dept_id', 'integer'),
            $this->column('deleted_at', 'datetime'),
        ]);
    }

    public function test_column_finds_by_name_and_returns_null_when_missing(): void
    {
        $table = $this->fixtureTable();

        $this->assertSame('title', $table->column('title')?->name);
        $this->assertNull($table->column('does_not_exist'));
    }

    public function test_has(): void
    {
        $table = $this->fixtureTable();

        $this->assertTrue($table->has('status'));
        $this->assertFalse($table->has('nope'));
    }

    public function test_has_status_and_has_soft_deletes_true_when_columns_present(): void
    {
        $table = $this->fixtureTable();

        $this->assertTrue($table->hasStatus());
        $this->assertTrue($table->hasSoftDeletes());
    }

    public function test_has_status_and_has_soft_deletes_false_when_columns_absent(): void
    {
        $table = new TableDefinition('files', '', [$this->column('id', 'integer', 'PRI', 'auto_increment')]);

        $this->assertFalse($table->hasStatus());
        $this->assertFalse($table->hasSoftDeletes());
    }

    public function test_creator_and_dept_columns_present(): void
    {
        $table = $this->fixtureTable();

        $this->assertSame('created_by', $table->creatorColumn());
        $this->assertSame('dept_id', $table->deptColumn());
    }

    public function test_creator_and_dept_columns_are_null_when_absent(): void
    {
        // 照 FileRepository 的先例：两列都没有时必须是 null，不是空字符串（spec §7.3 最后一行）
        $table = new TableDefinition('files', '', [$this->column('id', 'integer', 'PRI', 'auto_increment')]);

        $this->assertNull($table->creatorColumn());
        $this->assertNull($table->deptColumn());
    }

    public function test_unique_columns_only_include_uni_key_not_primary(): void
    {
        $table = $this->fixtureTable();

        $this->assertSame(['slug'], $table->uniqueColumns());
    }

    public function test_unique_columns_empty_when_none(): void
    {
        $table = new TableDefinition('plain', '', [$this->column('id', 'integer', 'PRI', 'auto_increment')]);

        $this->assertSame([], $table->uniqueColumns());
    }

    public function test_primary_key_reads_the_pri_marked_column(): void
    {
        $table = $this->fixtureTable();

        $this->assertSame('id', $table->primaryKey());
    }

    public function test_primary_key_defaults_to_id_when_no_column_is_marked_pri(): void
    {
        $table = new TableDefinition('plain', '', [$this->column('name')]);

        $this->assertSame('id', $table->primaryKey());
    }
}
