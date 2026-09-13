<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\ColumnDescriptor;
use tests\TestCase;

final class ColumnDescriptorTest extends TestCase
{
    private function makeColumn(): ColumnDescriptor
    {
        return new ColumnDescriptor(
            name: 'category',
            type: 'enum',
            rawType: "enum('news','tech','life')",
            nullable: false,
            default: 'news',
            comment: '分类',
            key: '',
            extra: '',
            formType: 'select',
            searchable: false,
            inList: true,
            inForm: true,
            enumValues: ['news', 'tech', 'life'],
        );
    }

    public function test_to_array_returns_the_twelve_contract_fields_without_enum_values(): void
    {
        $column = $this->makeColumn();

        $this->assertSame(
            [
                'name' => 'category',
                'type' => 'enum',
                'raw_type' => "enum('news','tech','life')",
                'nullable' => false,
                'default' => 'news',
                'comment' => '分类',
                'key' => '',
                'extra' => '',
                'form_type' => 'select',
                'searchable' => false,
                'in_list' => true,
                'in_form' => true,
            ],
            $column->toArray(),
        );
    }

    public function test_to_array_does_not_leak_enum_values(): void
    {
        $array = $this->makeColumn()->toArray();

        $this->assertArrayNotHasKey('enum_values', $array);
        $this->assertArrayNotHasKey('enumValues', $array);
        $this->assertCount(12, $array);
    }

    public function test_with_overrides_replaces_only_the_four_editable_fields(): void
    {
        $original = $this->makeColumn();

        $overridden = $original->withOverrides('input', true, false, false);

        $this->assertNotSame($original, $overridden);
        $this->assertSame('input', $overridden->formType);
        $this->assertTrue($overridden->searchable);
        $this->assertFalse($overridden->inList);
        $this->assertFalse($overridden->inForm);

        // 其余八个字段原样保留
        $this->assertSame($original->name, $overridden->name);
        $this->assertSame($original->type, $overridden->type);
        $this->assertSame($original->rawType, $overridden->rawType);
        $this->assertSame($original->nullable, $overridden->nullable);
        $this->assertSame($original->default, $overridden->default);
        $this->assertSame($original->comment, $overridden->comment);
        $this->assertSame($original->key, $overridden->key);
        $this->assertSame($original->extra, $overridden->extra);
        $this->assertSame($original->enumValues, $overridden->enumValues);
    }

    public function test_with_overrides_does_not_mutate_the_original(): void
    {
        $original = $this->makeColumn();
        $original->withOverrides('textarea', true, true, true);

        $this->assertSame('select', $original->formType);
        $this->assertFalse($original->searchable);
    }
}
