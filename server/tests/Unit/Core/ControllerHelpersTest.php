<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\base\Controller;
use support\Request;
use tests\TestCase;

final class HelperProbeController extends Controller
{
    /** @return array{0: int, 1: int} */
    public function page(Request $request): array
    {
        return $this->pageParams($request);
    }

    /** @return array<string, mixed> */
    public function payload(Request $request): array
    {
        return $this->body($request);
    }
}

final class ControllerHelpersTest extends TestCase
{
    private function request(string $method, string $uri, string $body = '', string $contentType = 'application/json'): Request
    {
        return new Request("{$method} {$uri} HTTP/1.1\r\nHost: localhost\r\nContent-Type: {$contentType}\r\nContent-Length: " . strlen($body) . "\r\n\r\n{$body}");
    }

    public function test_page_params_are_capped_at_100(): void
    {
        $controller = new HelperProbeController();

        $this->assertSame([2, 100], $controller->page($this->request('GET', '/x?page=2&limit=500')));
        $this->assertSame([1, 100], $controller->page($this->request('GET', '/x?page_no=1&page_size=9999')));
        $this->assertSame([1, 15], $controller->page($this->request('GET', '/x')));
        $this->assertSame([1, 1], $controller->page($this->request('GET', '/x?page=0&limit=0')));
    }

    public function test_body_reads_json_and_form_payloads(): void
    {
        $controller = new HelperProbeController();

        $this->assertSame(['a' => 1, 'b' => ['x']], $controller->payload($this->request('PUT', '/x', '{"a":1,"b":["x"]}')));
        $this->assertSame(['a' => '1'], $controller->payload($this->request('POST', '/x', 'a=1', 'application/x-www-form-urlencoded')));
        $this->assertSame([], $controller->payload($this->request('POST', '/x', 'not json')));
    }
}
