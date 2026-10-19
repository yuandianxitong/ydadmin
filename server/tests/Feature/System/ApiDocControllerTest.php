<?php

declare(strict_types=1);

namespace tests\Feature\System;

use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;
use tests\Support\TestResponse;

/** 文档需要管理端登录和 system.api_doc 权限，返回裸 OpenAPI JSON。 */
final class ApiDocControllerTest extends ApiTestCase
{
    use ConfigOverride;

    private function docGet(string $path, array $params = []): TestResponse
    {
        $admin = $this->actingAsAdmin(['system.api_doc']);
        return $this->get($path, $params, $admin->token);
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_production_document_requires_login_and_document_permission(): void
    {
        $this->overrideConfig('app.debug', false);
        $nobody = $this->actingAsAdmin();
        $member = $this->actingAsUser();
        $authorized = $this->actingAsAdmin(['system.api_doc']);
        foreach (['/adminapi/system/api-doc', '/adminapi/system/api-doc/openapi.json'] as $path) {
            $this->get($path)->assertCode(401);
            $this->get($path, [], $nobody->token)->assertCode(403);
            $this->get($path, [], $member->token)->assertCode(401);
            $this->assertSame(200, $this->get($path, [], $authorized->token)->status());
        }
    }

    public function test_openapi_json_is_authenticated_and_is_not_wrapped_in_the_envelope(): void
    {
        $response = $this->docGet('/adminapi/system/api-doc/openapi.json', ['type' => 'admin']);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('application/json', (string) $response->header('Content-Type'));
        $json = $response->json();
        $this->assertArrayNotHasKey('code', $json, 'openapi.json 不能套 {code,message,data,timestamp} 信封——它是裸 OpenAPI 文档，Swagger UI 直接读顶层字段');
        $this->assertSame('3.0.3', $json['openapi'] ?? null);
        $this->assertNotSame([], $json['paths'] ?? null);
    }

    public function test_index_html_is_authenticated_and_loads_swagger_ui_dist_5_from_cdn(): void
    {
        $response = $this->docGet('/adminapi/system/api-doc', ['type' => 'admin']);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/html', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('swagger-ui-dist@5', $response->body(), 'spec §5 决策 5：独立页与前端页一致，走 CDN，不落地本地副本');
        $this->assertStringContainsString('/adminapi/system/api-doc/openapi.json', $response->body(), '页面必须指向真正的 JSON 端点');
    }

    /**
     * $response->json() 用 json_decode(..., true)，对象 {} 与数组 [] 解码后都是 PHP []，
     * 用它判断不出编码层面是不是合法 OpenAPI——必须直接看原始 body 字符串，
     * 再用 json_decode(..., false) 复核顶层 paths 解出来是 stdClass 而不是 list。
     */
    public function test_api_openapi_json_contains_only_api_endpoints_and_keeps_paths_an_object(): void
    {
        foreach (['api', 'bogus-type-does-not-exist'] as $type) {
            $response = $this->docGet('/adminapi/system/api-doc/openapi.json', ['type' => $type]);

            $this->assertSame(200, $response->status(), "type={$type}");

            $decoded = json_decode($response->body(), false);
            $this->assertInstanceOf(\stdClass::class, $decoded->paths, "type={$type}：paths 解码回来必须是对象");
            $paths = array_keys((array) $decoded->paths);
            $this->assertContains('/api/common/sms-code', $paths, "type={$type}");
            foreach ($paths as $path) {
                $this->assertStringStartsWith('/api/', (string) $path, "type={$type}：混进了非 /api 端点 {$path}");
            }
        }
    }

    /**
     * OpenApiDocument::buildRequestBody() 对没有 {action}Rules() 方法的写端点（如
     * auth/refresh、auth/logout）产出 properties=[]，json_encode 会编成非法的 "properties": []。
     */
    public function test_admin_openapi_json_never_encodes_an_empty_properties_array(): void
    {
        $response = $this->docGet('/adminapi/system/api-doc/openapi.json', ['type' => 'admin']);

        $this->assertSame(200, $response->status());
        $this->assertDoesNotMatchRegularExpression(
            '/"properties":\s*\[\s*\]/',
            $response->body(),
            '任意端点的空 properties 都必须编成 {} 而不是 []，否则不是合法 OpenAPI 文档'
        );
    }

    /**
     * ?type[]=x 时 $request->get('type') 拿到的是数组；两个端点都不能因非法参数崩溃，
     * 不能因为一个不合法的查询参数就把 (string) 数组的 "Array to string conversion"
     * 警告放大成未捕获异常，返回 500（debug 下错误页可能带文件路径）。
     */
    public function test_array_type_query_parameter_does_not_crash_either_endpoint(): void
    {
        $openapi = $this->docGet('/adminapi/system/api-doc/openapi.json', ['type' => ['x']]);
        $this->assertSame(200, $openapi->status(), $openapi->body());

        $index = $this->docGet('/adminapi/system/api-doc', ['type' => ['x']]);
        $this->assertSame(200, $index->status(), $index->body());
    }

    /**
     * F7：两个端点对 type 的归一必须同源。旧版 ?type=bogus 时 openapi.json 按 'api' 给空文档，
     * index 页却指向 'admin'——同一个参数两种解读。
     */
    public function test_both_endpoints_normalise_an_unknown_type_identically(): void
    {
        $openapi = $this->docGet('/adminapi/system/api-doc/openapi.json', ['type' => 'bogus']);
        $this->assertSame(200, $openapi->status());
        $this->assertStringContainsString('/api/common/sms-code', $openapi->body(), '未知 type 归一为 api：拿到的是 C 端文档');
        $this->assertStringNotContainsString('/adminapi/', $openapi->body(), '未知 type 不能被猜成 admin');

        $index = $this->docGet('/adminapi/system/api-doc', ['type' => 'bogus']);
        $this->assertSame(200, $index->status());
        $this->assertStringContainsString('openapi.json?type=api', $index->body(), 'index 页必须与 openapi.json 用同一个归一结果');
    }
}
