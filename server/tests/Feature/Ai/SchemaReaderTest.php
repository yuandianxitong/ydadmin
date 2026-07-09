<?php
// server/tests/Feature/Ai/SchemaReaderTest.php（依赖本地 DB，menus 表必然存在）
namespace tests\Feature\Ai;

use core\ai\AiClientException;
use core\ai\SchemaReader;
use tests\TestCase;

class SchemaReaderTest extends TestCase
{
    public function testBuildSchemaInputForExistingTable(): void
    {
        $input = (new SchemaReader())->buildSchemaInput(['menus']);
        $this->assertSame('menus', $input['tables'][0]['name']);
        $col = $input['tables'][0]['columns'][0];
        foreach (['name', 'type', 'key', 'default', 'comment', 'nullable'] as $k) {
            $this->assertArrayHasKey($k, $col);
        }
    }

    public function testUnknownTableThrows(): void
    {
        $this->expectException(AiClientException::class);
        (new SchemaReader())->buildSchemaInput(['definitely_not_a_table_xyz']);
    }
}
