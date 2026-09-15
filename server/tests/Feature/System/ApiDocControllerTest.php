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
}
