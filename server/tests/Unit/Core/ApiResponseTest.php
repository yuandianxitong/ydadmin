<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\response\Api;
use support\Response;
use tests\TestCase;

final class ApiResponseTest extends TestCase
{
    /** @return array<string, mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->rawBody(), true);
    }

    public function test_success_envelope(): void
    {
        $response = Api::success(['a' => 1]);
        $body = $this->body($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['code', 'message', 'data', 'timestamp'], array_keys($body));
        $this->assertSame(200, $body['code']);
        $this->assertSame('操作成功', $body['message']);
        $this->assertSame(['a' => 1], $body['data']);
        $this->assertIsInt($body['timestamp']);
        $this->assertSame('application/json', $response->getHeader('Content-Type'));
    }

    public function test_error_keeps_http_200_and_carries_business_code(): void
    {
        $response = Api::error('参数错误');
        $body = $this->body($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(400, $body['code']);
        $this->assertSame('参数错误', $body['message']);
        $this->assertSame([], $body['data']);
    }

    public function test_error_can_carry_data(): void
    {
        $body = $this->body(Api::error('校验失败', 422, ['errors' => ['name' => '必填']]));
        $this->assertSame(['errors' => ['name' => '必填']], $body['data']);
    }

    public function test_error_with_status_sets_http_status(): void
    {
        $response = Api::errorWithStatus('服务器内部错误', 500);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(500, $this->body($response)['code']);
    }

    public function test_paginate_passes_repository_result_through(): void
    {
        $result = ['list' => [['id' => 1]], 'pagination' => ['current_page' => 1, 'per_page' => 15, 'total' => 1, 'last_page' => 1]];
        $this->assertSame($result, $this->body(Api::paginate($result))['data']);
    }

    public function test_unicode_is_not_escaped(): void
    {
        $this->assertStringContainsString('操作成功', (string) Api::success()->rawBody());
    }

    public function test_unencodable_data_falls_back_to_safe_500(): void
    {
        $response = Api::success(["\xB1\x31"]);
        $body = $this->body($response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(500, $body['code']);
        $this->assertSame('响应序列化失败', $body['message']);
    }
}
