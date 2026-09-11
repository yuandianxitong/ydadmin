<?php

declare(strict_types=1);

namespace tests\Unit\Http;

use app\adminapi\controller\HealthController;
use tests\TestCase;
use Webman\Http\Request;

final class HealthControllerTest extends TestCase
{
    public function test_health_reports_status_and_version(): void
    {
        $request = new Request("GET /adminapi/health HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $body = json_decode((string) (new HealthController())->index($request)->rawBody(), true);

        $this->assertSame(200, $body['code']);
        $this->assertSame(['status' => 'ok', 'version' => '2.0.0-dev'], $body['data']);
    }
}
