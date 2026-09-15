<?php

declare(strict_types=1);

namespace tests\Unit\ApiDoc;

use core\apidoc\EndpointDescriptor;
use core\apidoc\EnvelopeSchemas;
use core\apidoc\OpenApiDocument;
use core\apidoc\RuleTranslator;
use tests\TestCase;

final class OpenApiDocumentTest extends TestCase
{
    private const CONTROLLER = 'app\\adminapi\\controller\\system\\DictionaryController';

    private function document(): OpenApiDocument
    {
        return new OpenApiDocument(new RuleTranslator(), 'YDAdmin API', '2.0.0', 'http://localhost:8787');
    }

    /**
     * 'show' 故意用路由表里真实的原始写法 `{id:\d+}`(见 server/config/route.php 第 117 行)
     * 而不是契约注释里简化过的 `{id}`:EndpointDescriptor::pathParameters() 的文档明写
     * 「从 path 里的 {id:\d+} 解析,\d+ → integer」,这条推断规则要成立,$path 就必须保留
     * 原始的类型约束写法,否则 pathParameters() 无从判断 integer 还是 string。据此,
     * OpenApiDocument 组装 `paths` 的 key 时必须把 `{id:\d+}` 清洗成 OpenAPI 合法的
     * `{id}`——这正是本任务要覆盖的一处,见下面 test_path_key_strips_the_route_regex_suffix。
     */
    private function endpoints(): array
    {
        return [
            new EndpointDescriptor('GET', '/adminapi/system/dictionary/{id:\d+}', self::CONTROLLER, 'show', 'system.dictionary.list', false, 'system'),
            new EndpointDescriptor('GET', '/adminapi/system/dictionary/options', self::CONTROLLER, 'options', null, true, 'system'),
            new EndpointDescriptor('POST', '/adminapi/system/dictionary', self::CONTROLLER, 'store', 'system.dictionary.create', false, 'system'),
        ];
    }

    /** @return array<string, array<string, string>|null> */
    private function rulesByOperationId(): array
    {
        return [
            'DictionaryController::show'    => null,
            'DictionaryController::options' => ['keyword' => 'nullable|string|max:100', 'mobile' => 'nullable|regex:/^1[3-9]\d{9}$/'],
            'DictionaryController::store'   => ['name' => 'required|string|max:50', 'sort' => 'sometimes|integer|min:0'],
        ];
    }

    public function test_top_level_document_shape(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), ['既有警告:占位']);

        $this->assertStringStartsWith('3.0.', $doc['openapi']);
        $this->assertSame('YDAdmin API', $doc['info']['title']);
        $this->assertSame('2.0.0', $doc['info']['version']);
        $this->assertSame('http://localhost:8787', $doc['servers'][0]['url']);
        $this->assertSame([['bearerAuth' => []]], $doc['security']);
        $this->assertSame('http', $doc['components']['securitySchemes']['bearerAuth']['type']);
        $this->assertSame('bearer', $doc['components']['securitySchemes']['bearerAuth']['scheme']);
        $this->assertSame(EnvelopeSchemas::all(), $doc['components']['schemas']);
        $this->assertContains('既有警告:占位', $doc['x-doc-warnings']);
    }

    public function test_path_parameter_is_derived_from_the_route_and_typed_integer(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);
        $get = $doc['paths']['/adminapi/system/dictionary/{id}']['get'];

        $this->assertSame('DictionaryController::show', $get['operationId']);
        $this->assertSame(['system'], $get['tags']);
        $this->assertSame(['id' => 'path'], ['id' => $get['parameters'][0]['in']]);
        $this->assertSame('id', $get['parameters'][0]['name']);
        $this->assertTrue($get['parameters'][0]['required']);
        $this->assertSame('integer', $get['parameters'][0]['schema']['type']);
        $this->assertArrayNotHasKey('requestBody', $get);
    }

    /**
     * EndpointDescriptor::$path 保留路由表原始写法 `{id:\d+}`(pathParameters() 靠它判定
     * integer/string),但 OpenAPI 3.0 的 paths 对象 key 只认 `{id}` 这种不带类型约束的
     * 花括号写法。组装时必须把前者清洗成后者,否则 Swagger UI 解析这份文档时,paths key
     * 里的 `{id:\d+}` 与 parameters[].name 里的 `id` 对不上。
     */
    public function test_path_key_strips_the_route_regex_suffix(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);

        $this->assertArrayHasKey('/adminapi/system/dictionary/{id}', $doc['paths']);
        $this->assertArrayNotHasKey('/adminapi/system/dictionary/{id:\d+}', $doc['paths']);
    }

    public function test_permission_code_is_written_to_x_permission_and_prepended_to_description(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);
        $get = $doc['paths']['/adminapi/system/dictionary/{id}']['get'];

        $this->assertSame('system.dictionary.list', $get['x-permission']);
        $this->assertStringContainsString('system.dictionary.list', $get['description']);
    }

    public function test_permission_skip_is_marked_as_unauthenticated(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);
        $get = $doc['paths']['/adminapi/system/dictionary/options']['get'];

        $this->assertNull($get['x-permission']);
        $this->assertStringContainsString('免鉴权', $get['description']);
    }

    public function test_get_endpoint_exposes_query_parameters_translated_by_rule_translator(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);
        $get = $doc['paths']['/adminapi/system/dictionary/options']['get'];

        $byName = [];
        foreach ($get['parameters'] as $param) {
            $byName[$param['name']] = $param;
        }

        $this->assertSame('query', $byName['keyword']['in']);
        $this->assertFalse($byName['keyword']['required']);
        $this->assertSame('string', $byName['keyword']['schema']['type']);
        $this->assertSame(100, $byName['keyword']['schema']['maxLength']);
        $this->assertArrayNotHasKey('requestBody', $get);
        $this->assertArrayNotHasKey('422', $get['responses']);
    }

    public function test_post_endpoint_builds_request_body_instead_of_query_parameters(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);
        $post = $doc['paths']['/adminapi/system/dictionary']['post'];

        $this->assertArrayNotHasKey('parameters', $post);
        $bodySchema = $post['requestBody']['content']['application/json']['schema'];
        $this->assertSame(['name'], $bodySchema['required']);
        $this->assertSame('string', $bodySchema['properties']['name']['type']);
        $this->assertSame(50, $bodySchema['properties']['name']['maxLength']);
        $this->assertSame('integer', $bodySchema['properties']['sort']['type']);
        $this->assertArrayHasKey('422', $post['responses']);
        $this->assertSame(
            '#/components/schemas/' . EnvelopeSchemas::VALIDATION,
            $post['responses']['422']['content']['application/json']['schema']['$ref'],
        );
    }

    public function test_response_200_documents_all_three_generic_envelope_shapes_via_oneof(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);
        $refs = array_column($doc['paths']['/adminapi/system/dictionary/{id}']['get']['responses']['200']['content']['application/json']['schema']['oneOf'], '$ref');

        $this->assertSame(
            [
                '#/components/schemas/' . EnvelopeSchemas::SUCCESS,
                '#/components/schemas/' . EnvelopeSchemas::ERROR,
                '#/components/schemas/' . EnvelopeSchemas::PAGINATED,
            ],
            $refs,
        );
    }

    /**
     * regex 规则的原始串按 spec §7「四个不干净的」剥定界符写进 pattern 之余,原样附一份到
     * notes(因为 ECMA-262 的 pattern 丢 `u` 修饰符);OpenApiDocument 必须把这份 notes
     * 一起并进文档级 x-doc-warnings,不能只留在字段自己的 description 里孤立存在。
     */
    public function test_rule_translator_notes_are_folded_into_document_level_warnings(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);

        $matched = array_filter(
            $doc['x-doc-warnings'],
            static fn (string $w): bool => str_contains($w, 'DictionaryController::options') && str_contains($w, 'mobile'),
        );
        $this->assertNotEmpty($matched, 'x-doc-warnings 里应该有一条提到该端点与 mobile 字段的翻译警告');
    }

    public function test_every_operation_has_a_responses_key(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);

        foreach ($doc['paths'] as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $this->assertArrayHasKey('responses', $operation, "{$httpMethod} {$path}");
                $this->assertNotEmpty($operation['responses'], "{$httpMethod} {$path}");
            }
        }
    }

    public function test_every_dollar_ref_resolves_inside_components(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);
        $refs = [];
        $this->collectRefs($doc, $refs);

        $this->assertNotEmpty($refs, '这份文档至少应该有一个 $ref(响应信封)');
        foreach ($refs as $ref) {
            $this->assertStringStartsWith('#/components/', $ref);
            $pointer = explode('/', substr($ref, 2));
            $node = $doc;
            foreach ($pointer as $segment) {
                $this->assertArrayHasKey($segment, $node, "{$ref} 解析失败于段 {$segment}");
                $node = $node[$segment];
            }
        }
    }

    /**
     * normalizePathTemplate() 是公开静态纯函数,Task 13 的路由表↔文档双射测试要直接调用
     * 它去规范化路由表那一侧;这里单独锁定其行为,防止未来重构悄悄改变清洗规则。
     */
    public function test_normalize_path_template_strips_regex_type_constraints(): void
    {
        $this->assertSame(
            '/adminapi/system/dictionary/{id}/items',
            OpenApiDocument::normalizePathTemplate('/adminapi/system/dictionary/{id:\d+}/items'),
        );
    }

    public function test_normalize_path_template_leaves_path_without_params_unchanged(): void
    {
        $this->assertSame(
            '/adminapi/system/dictionary/options',
            OpenApiDocument::normalizePathTemplate('/adminapi/system/dictionary/options'),
        );
    }

    /**
     * build() 对空端点列表返回 paths=[]（合法的 PHP 空数组，Task 13 的双射测试按数组读它）。
     * 但 json_encode([]) 编成 JSON `[]`，OpenAPI 3.0 规定 paths 是对象——toJson() 必须
     * 在编码这一步把它换成 `{}`，不能影响 build() 返回给 PHP 调用方的数组本身。
     */
    public function test_to_json_encodes_an_empty_paths_array_as_a_json_object(): void
    {
        $doc = $this->document()->build([], [], []);
        $this->assertSame([], $doc['paths'], 'build() 返回给 PHP 调用方的仍是空数组，不受 toJson() 影响');

        $json = OpenApiDocument::toJson($doc);

        $this->assertMatchesRegularExpression('/"paths":\s*\{\}/', $json);
        $decoded = json_decode($json, false);
        $this->assertInstanceOf(\stdClass::class, $decoded->paths);
    }

    /**
     * 没有规则方法的写端点（如生成的控制器里没有 xxxRules()）反射不出规则，buildRequestBody()
     * 对应产出 properties=[]。同样必须在 toJson() 里换成 {}，否则是非法 OpenAPI 文档。
     */
    public function test_to_json_encodes_an_empty_request_body_properties_array_as_a_json_object(): void
    {
        $endpoints = [
            new EndpointDescriptor('POST', '/adminapi/system/dictionary', self::CONTROLLER, 'store', 'system.dictionary.create', false, 'system'),
        ];
        $doc = $this->document()->build($endpoints, ['DictionaryController::store' => null], []);
        $bodySchema = $doc['paths']['/adminapi/system/dictionary']['post']['requestBody']['content']['application/json']['schema'];
        $this->assertSame([], $bodySchema['properties'], 'build() 返回给 PHP 调用方的仍是空数组');

        $json = OpenApiDocument::toJson($doc);

        $this->assertDoesNotMatchRegularExpression('/"properties":\s*\[\s*\]/', $json);
        $this->assertMatchesRegularExpression('/"properties":\s*\{\}/', $json);
    }

    /** 非空的 paths / properties 原样编码，toJson() 的递归替换不能牵连正常数据。 */
    public function test_to_json_leaves_non_empty_content_unchanged(): void
    {
        $doc = $this->document()->build($this->endpoints(), $this->rulesByOperationId(), []);

        $roundTripped = json_decode(OpenApiDocument::toJson($doc), true);

        $this->assertSame($doc, $roundTripped);
    }

    /** @param array<string, mixed> $node @param list<string> $refs */
    private function collectRefs(array $node, array &$refs): void
    {
        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                $refs[] = $value;
                continue;
            }
            if (is_array($value)) {
                $this->collectRefs($value, $refs);
            }
        }
    }
}
