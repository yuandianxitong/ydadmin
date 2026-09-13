<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\GeneratorRequest;
use tests\TestCase;

final class GeneratorRequestTest extends TestCase
{
    public function test_constructor_stores_all_properties_verbatim(): void
    {
        $overrides = [
            'title' => ['form_type' => 'textarea', 'searchable' => true, 'in_list' => true, 'in_form' => true],
        ];

        $request = new GeneratorRequest(
            tableName: 'gen_articles',
            moduleName: 'business',
            modelName: 'GenArticle',
            tableComment: '生成器夹具表',
            overrides: $overrides,
        );

        $this->assertSame('gen_articles', $request->tableName);
        $this->assertSame('business', $request->moduleName);
        $this->assertSame('GenArticle', $request->modelName);
        $this->assertSame('生成器夹具表', $request->tableComment);
        $this->assertSame($overrides, $request->overrides);
    }

    public function test_overrides_can_be_an_empty_array(): void
    {
        $request = new GeneratorRequest('gen_articles', 'business', 'GenArticle', '', []);

        $this->assertSame([], $request->overrides);
    }
}
