<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\ApiAuthMiddleware;
use core\auth\TokenManager;
use core\context\RequestContext;
use core\response\Api;
use tests\TestCase;
use Webman\Http\Request;

final class ApiAuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TokenManager::flushInstances();
    }

    private function request(?string $token): Request
    {
        $auth = $token !== null ? "Authorization: Bearer {$token}\r\n" : '';
        return new Request("GET /api/demo HTTP/1.1\r\nHost: localhost\r\n{$auth}\r\n");
    }

    public function test_valid_user_token_sets_user_id_but_not_acting_user(): void
    {
        $request = $this->request(TokenManager::scope('user')->generate(['user_id' => 42]));
        $response = (new ApiAuthMiddleware())->process($request, fn () => Api::success());

        $this->assertSame(200, json_decode((string) $response->rawBody(), true)['code']);
        $this->assertSame(42, $request->userId);
        $this->assertSame(0, RequestContext::actingUser(), 'acting user 只代表管理员（M1 数据权限依赖这一点）');
    }

    public function test_admin_token_and_missing_token_are_401(): void
    {
        foreach ([TokenManager::scope('admin')->generate(['admin_id' => 1]), null] as $token) {
            $response = (new ApiAuthMiddleware())->process($this->request($token), fn () => Api::success());
            $this->assertSame(401, json_decode((string) $response->rawBody(), true)['code']);
        }
    }
}
