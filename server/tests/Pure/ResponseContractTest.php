<?php

declare(strict_types=1);

namespace tests\Pure;

use app\resource\announcement\AnnouncementResource;
use core\apidoc\EndpointDescriptor;
use core\apidoc\OpenApiDocument;
use core\apidoc\ResponseReflector;
use core\apidoc\RuleTranslator;
use core\apidoc\TypeScriptGenerator;
use core\response\ResponseSchema;
use PHPUnit\Framework\TestCase;

final class ResponseProbe
{
    #[ResponseSchema(AnnouncementResource::class, paginated: true)]
    public function index(): void
    {
    }
}

final class ResponseContractTest extends TestCase
{
    public function test_declared_fields_control_runtime_output_and_paginated_document(): void
    {
        $page = ['list' => [['id' => 1, 'title' => 'Hello', 'password' => 'secret']], 'pagination' => ['current_page' => 1, 'per_page' => 15, 'total' => 1, 'last_page' => 1]];
        $presented = AnnouncementResource::page($page);
        $this->assertSame(['id' => 1, 'title' => 'Hello'], $presented['list'][0]);
        $this->assertSame($page['pagination'], $presented['pagination']);
        $schema = (new ResponseReflector())->schemaFor(ResponseProbe::class, 'index');
        $this->assertSame(AnnouncementResource::schema(), $schema['properties']['list']['items']);
    }

    public function test_openapi_and_typescript_use_declared_success_shape(): void
    {
        $endpoint = new EndpointDescriptor('GET', '/adminapi/announcements', ResponseProbe::class, 'index', 'announcement.list', false, 'announcement', true);
        $schema = (new ResponseReflector())->schemaFor(ResponseProbe::class, 'index');
        $document = (new OpenApiDocument(new RuleTranslator(), 'Test', '1', '/'))->build([$endpoint], [], [], [$endpoint->operationId() => $schema]);
        $response = $document['paths']['/adminapi/announcements']['get']['responses'][200]['content']['application/json']['schema'];
        $this->assertSame($schema, $response['oneOf'][0]['properties']['data']);
        $types = (new TypeScriptGenerator())->generate($document);
        $this->assertStringContainsString('"GET /adminapi/announcements"', $types);
        $this->assertStringContainsString('"id": number', $types);
        $this->assertStringContainsString('"publish_at"?: (string | null)', $types);
        $this->assertStringContainsString('"code": 200', $types);
    }

    public function test_typescript_keeps_required_queries_references_and_unknown_json(): void
    {
        $document = [
            'components' => ['schemas' => ['Payload' => ['type' => 'array', 'items' => []]]],
            'paths' => ['/x/{id}' => ['post' => [
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                ],
                'requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Payload']]]],
            ]]],
        ];
        $types = (new TypeScriptGenerator())->generate($document);
        $this->assertStringContainsString('path: { "id": number; }', $types);
        $this->assertStringContainsString('query: { "q"?: string; }', $types);
        $this->assertStringContainsString('body: ApiSchemas["Payload"]', $types);
        $this->assertStringContainsString('Array<unknown>', $types);
    }
}
