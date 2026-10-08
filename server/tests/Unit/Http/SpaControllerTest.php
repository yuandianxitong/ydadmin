<?php

declare(strict_types=1);

namespace tests\Unit\Http;

use app\controller\SpaController;
use tests\TestCase;
use Webman\Http\Request;

final class SpaControllerTest extends TestCase
{
    private function request(string $path, string $ua = 'Mozilla/5.0 (Macintosh)'): Request
    {
        return new Request("GET {$path} HTTP/1.1\r\nHost: localhost\r\nUser-Agent: {$ua}\r\n\r\n");
    }

    public function test_admin_serves_index_html(): void
    {
        $response = (new SpaController())->admin($this->request('/admin/system/admin'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));
        $this->assertSame(file_get_contents(public_path() . '/admin/index.html'), (string) $response->rawBody());
    }

    public function test_undeployed_app_returns_404_text(): void
    {
        if (is_file(public_path() . '/mobile/index.html')) {
            $this->markTestSkipped('mobile 已部署');
        }
        $response = (new SpaController())->mobile($this->request('/mobile/'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Mobile site not deployed', (string) $response->rawBody());
    }

    public function test_home_redirects_by_user_agent(): void
    {
        $mobile = (new SpaController())->home($this->request('/', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile'));
        $desktop = (new SpaController())->home($this->request('/'));

        $this->assertSame(302, $mobile->getStatusCode());
        $mobileHome = is_file(public_path() . '/mobile/index.html') ? '/mobile/' : '/pc/';
        $this->assertSame($mobileHome, $mobile->getHeader('Location'));
        $this->assertSame('/pc/', $desktop->getHeader('Location'));
    }
}
