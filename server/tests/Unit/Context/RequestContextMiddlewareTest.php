<?php

declare(strict_types=1);

namespace tests\Unit\Context;

use app\middleware\RequestContextMiddleware;
use core\context\RequestContext;
use core\response\Api;
use tests\TestCase;
use Webman\Http\Request;

final class RequestContextMiddlewareTest extends TestCase
{
    public function test_inbound_trace_is_propagated_to_context_and_response(): void
    {
        $trace = 'trace_1726000000000_abc123def';
        $request = new Request("GET /adminapi/health HTTP/1.1\r\nHost: localhost\r\nX-Trace-Id: {$trace}\r\n\r\n");
        $seen = null;
        $response = (new RequestContextMiddleware())->process($request, function () use (&$seen) {
            $seen = RequestContext::traceId();
            return Api::success();
        });

        $this->assertSame($trace, $seen);
        $this->assertSame($trace, $response->getHeader('X-Trace-Id'));
    }

    public function test_trace_is_generated_when_absent(): void
    {
        $request = new Request("GET /adminapi/health HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $response = (new RequestContextMiddleware())->process($request, fn () => Api::success());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $response->getHeader('X-Trace-Id'));
    }
}
