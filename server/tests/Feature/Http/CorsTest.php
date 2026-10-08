<?php

declare(strict_types=1);

namespace tests\Feature\Http;

use tests\Support\ApiTestCase;

final class CorsTest extends ApiTestCase
{
    private const ALLOWED = 'http://allowed.test';

    public function test_allowed_origin_gets_cors_headers(): void
    {
        $response = $this->get('/adminapi/health', [], null, ['Origin' => self::ALLOWED]);

        $this->assertSame(self::ALLOWED, $response->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->header('Access-Control-Allow-Credentials'));
        $this->assertSame('Origin', $response->header('Vary'));
        $this->assertSame('X-Trace-Id', $response->header('Access-Control-Expose-Headers'));
    }

    public function test_other_or_missing_origins_get_no_cors_headers(): void
    {
        $this->assertNull($this->get('/adminapi/health', [], null, ['Origin' => 'http://evil.test'])->header('Access-Control-Allow-Origin'));
        $this->assertNull($this->get('/adminapi/health')->header('Access-Control-Allow-Origin'));
    }

    public function test_preflight_from_allowed_origin_is_204(): void
    {
        $response = $this->call('OPTIONS', '/adminapi/auth/login', [], null, ['Origin' => self::ALLOWED, 'Access-Control-Request-Method' => 'POST']);

        $this->assertSame(204, $response->status());
        $this->assertSame(self::ALLOWED, $response->header('Access-Control-Allow-Origin'));
        $this->assertSame('Authorization, Content-Type, think-lang, X-Trace-Id, X-Client-Type', $response->header('Access-Control-Allow-Headers'));
        $this->assertSame('GET, POST, PUT, DELETE, OPTIONS', $response->header('Access-Control-Allow-Methods'));
    }

    public function test_preflight_from_other_origin_is_403(): void
    {
        $this->assertSame(403, $this->call('OPTIONS', '/adminapi/auth/login', [], null, ['Origin' => 'http://evil.test'])->status());
    }

    public function test_unmatched_route_from_allowed_origin_carries_cors_headers(): void
    {
        $response = $this->call('GET', '/adminapi/no-such-route', [], null, ['Origin' => self::ALLOWED]);

        $this->assertSame(404, $response->status());
        $this->assertSame(self::ALLOWED, $response->header('Access-Control-Allow-Origin'));
    }

    public function test_error_responses_also_carry_cors_headers(): void
    {
        // 跨域部署时前端要能读到 401 才会跳登录页
        $response = $this->get('/adminapi/auth/info', [], null, ['Origin' => self::ALLOWED]);

        $response->assertCode(401);
        $this->assertSame(self::ALLOWED, $response->header('Access-Control-Allow-Origin'));
    }
}
