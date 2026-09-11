<?php

declare(strict_types=1);

namespace tests\Unit\Http;

use app\middleware\StaticFile;
use tests\TestCase;
use Webman\Http\Request;

final class StaticFileTest extends TestCase
{
    private function request(string $path): Request
    {
        return new Request("GET {$path} HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    public function test_dotfile_is_forbidden(): void
    {
        $response = (new StaticFile())->process($this->request('/admin/.env'), fn () => response('ok'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_percent_encoded_uppercase_dotfile_is_forbidden(): void
    {
        $response = (new StaticFile())->process($this->request('/admin/%2Eenv'), fn () => response('ok'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_percent_encoded_lowercase_dotfile_is_forbidden(): void
    {
        $response = (new StaticFile())->process($this->request('/admin/%2eenv'), fn () => response('ok'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_normal_file_passes_through_with_nosniff_header(): void
    {
        $response = (new StaticFile())->process($this->request('/admin/favicon.ico'), fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
    }
}
