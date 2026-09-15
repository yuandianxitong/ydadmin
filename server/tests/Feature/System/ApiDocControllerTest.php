<?php

declare(strict_types=1);

namespace tests\Feature\System;

use tests\Support\ApiTestCase;

/**
 * ApiDocController 两个动作：openapi() 返回裸 OpenAPI JSON（不套响应信封），
 * index() 返回独立 Swagger 页（HTML，CDN 加载 swagger-ui-dist@5）。两条路由都不需要登录——
 * 前端「Swagger UI」「下载 JSON」两个按钮走 window.open，浏览器导航带不了 Authorization 头。
 */
final class ApiDocControllerTest extends ApiTestCase
{
    public function test_openapi_json_is_reachable_without_auth_and_is_not_wrapped_in_the_envelope(): void
    {
        $response = $this->get('/adminapi/system/api-doc/openapi.json', ['type' => 'admin']);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('application/json', (string) $response->header('Content-Type'));
        $json = $response->json();
        $this->assertArrayNotHasKey('code', $json, 'openapi.json 不能套 {code,message,data,timestamp} 信封——它是裸 OpenAPI 文档，Swagger UI 直接读顶层字段');
        $this->assertSame('3.0.3', $json['openapi'] ?? null);
        $this->assertNotSame([], $json['paths'] ?? null);
    }

    public function test_index_html_is_reachable_without_auth_and_loads_swagger_ui_dist_5_from_cdn(): void
    {
        $response = $this->get('/adminapi/system/api-doc', ['type' => 'admin']);

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
    public function test_openapi_json_with_no_routes_encodes_empty_paths_as_an_object_not_an_array(): void
    {
        foreach (['api', 'bogus-type-does-not-exist'] as $type) {
            $response = $this->get('/adminapi/system/api-doc/openapi.json', ['type' => $type]);

            $this->assertSame(200, $response->status(), "type={$type}");
            $this->assertMatchesRegularExpression(
                '/"paths":\s*\{\}/',
                $response->body(),
                "type={$type}：本仓库没有 /api 路由，空 paths 编码必须是 JSON 对象 {} 而不是数组 []，原始 body：" . $response->body()
            );

            $decoded = json_decode($response->body(), false);
            $this->assertInstanceOf(\stdClass::class, $decoded->paths, "type={$type}：paths 解码回来必须是对象");
        }
    }

    /**
     * OpenApiDocument::buildRequestBody() 对没有 {action}Rules() 方法的写端点（如
     * auth/refresh、auth/logout）产出 properties=[]，json_encode 会编成非法的 "properties": []。
     */
    public function test_admin_openapi_json_never_encodes_an_empty_properties_array(): void
    {
        $response = $this->get('/adminapi/system/api-doc/openapi.json', ['type' => 'admin']);

        $this->assertSame(200, $response->status());
        $this->assertDoesNotMatchRegularExpression(
            '/"properties":\s*\[\s*\]/',
            $response->body(),
            '任意端点的空 properties 都必须编成 {} 而不是 []，否则不是合法 OpenAPI 文档'
        );
    }

    /**
     * ?type[]=x 时 $request->get('type') 拿到的是数组；两个端点都必须公开、免鉴权，
     * 不能因为一个不合法的查询参数就把 (string) 数组的 "Array to string conversion"
     * 警告放大成未捕获异常，返回 500（debug 下错误页可能带文件路径）。
     */
    public function test_array_type_query_parameter_does_not_crash_either_endpoint(): void
    {
        $openapi = $this->get('/adminapi/system/api-doc/openapi.json', ['type' => ['x']]);
        $this->assertSame(200, $openapi->status(), $openapi->body());

        $index = $this->get('/adminapi/system/api-doc', ['type' => ['x']]);
        $this->assertSame(200, $index->status(), $index->body());
    }
}
