<?php

declare(strict_types=1);

namespace tests\Feature;

use core\context\RequestContext;
use tests\Support\ApiTestCase;

final class PipelineHarnessTest extends ApiTestCase
{
    public function test_health_goes_through_the_real_pipeline(): void
    {
        $response = $this->get('/adminapi/health');

        $this->assertSame(200, $response->status());
        $response->assertOk();
        $this->assertSame('ok', $response->data()['status']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $response->header('x-trace-id'));
    }

    public function test_inbound_trace_id_is_echoed(): void
    {
        $response = $this->get('/adminapi/health', [], null, ['X-Trace-Id' => 'trace_1726000000000_abc123def']);

        $this->assertSame('trace_1726000000000_abc123def', $response->header('X-Trace-Id'));
    }

    public function test_unknown_route_is_http_404_with_envelope(): void
    {
        $response = $this->get('/adminapi/does-not-exist');

        $this->assertSame(404, $response->status());
        $response->assertCode(404);
    }

    public function test_spa_route_returns_html(): void
    {
        $response = $this->get('/admin/system/admin');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/html', (string) $response->header('Content-Type'));
    }

    public function test_context_is_destroyed_after_each_request(): void
    {
        $this->get('/adminapi/health');

        $this->assertSame('', RequestContext::traceId(), 'webman 在响应发出后销毁 Context，夹具必须保持这一行为');
    }

    public function test_json_body_reaches_the_controller(): void
    {
        // 未知路由也会经过完整管道；这里只验证 POST 能被正确解析（返回 404 而不是 500）
        $this->post('/adminapi/does-not-exist', ['a' => 1])->assertCode(404);
    }
}
