<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\AdminAuthMiddleware;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\context\RequestContext;
use core\response\Api;
use support\Redis;
use tests\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

final class AdminAuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TokenManager::flushInstances();
    }

    protected function tearDown(): void
    {
        Redis::del('admin_token_ver:5', 'admin_token_ver:77');
        parent::tearDown();
    }

    private function request(?string $token): Request
    {
        $auth = $token !== null ? "Authorization: Bearer {$token}\r\n" : '';
        return new Request("GET /adminapi/demo HTTP/1.1\r\nHost: localhost\r\n{$auth}\r\n");
    }

    private function code(Response $response): int
    {
        return (int) json_decode((string) $response->rawBody(), true)['code'];
    }

    public function test_missing_token_is_401(): void
    {
        $response = (new AdminAuthMiddleware())->process($this->request(null), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
        $this->assertSame(lang('auth.please_login'), json_decode((string) $response->rawBody(), true)['message']);
    }

    public function test_valid_admin_token_passes_and_sets_identity(): void
    {
        $token = TokenManager::scope('admin')->generate(['admin_id' => 5, 'username' => 'alice', 'ver' => TokenVersion::current(5)]);
        $request = $this->request($token);

        $response = (new AdminAuthMiddleware())->process($request, fn () => Api::success());

        $this->assertSame(200, $this->code($response));
        $this->assertSame(5, $request->userId);
        $this->assertSame('alice', $request->username);
        $this->assertSame(5, RequestContext::actingUser());
    }

    public function test_valid_token_exposes_its_version_and_jti_on_the_request(): void
    {
        $manager = TokenManager::scope('admin');
        $token = $manager->generate(['admin_id' => 5, 'username' => 'alice', 'ver' => TokenVersion::current(5)]);
        $request = $this->request($token);

        (new AdminAuthMiddleware())->process($request, fn () => Api::success());

        $this->assertSame(TokenVersion::current(5), $request->tokenVer);
        $this->assertSame($manager->verifyClaims($token)['jti'], $request->tokenJti);
    }

    public function test_user_scope_token_is_401(): void
    {
        $token = TokenManager::scope('user')->generate(['user_id' => 5]);
        $response = (new AdminAuthMiddleware())->process($this->request($token), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }

    public function test_token_without_admin_id_is_401(): void
    {
        $token = TokenManager::scope('admin')->generate(['username' => 'ghost']);
        $response = (new AdminAuthMiddleware())->process($this->request($token), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }

    public function test_token_without_version_claim_is_401(): void
    {
        // 版本号有随机基数、永不为 0，不带 ver 的 token 按 0 比对，一律失效
        $token = TokenManager::scope('admin')->generate(['admin_id' => 5, 'username' => 'alice']);
        $response = (new AdminAuthMiddleware())->process($this->request($token), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }

    public function test_blacklisted_token_is_401(): void
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->generate(['admin_id' => 5, 'username' => 'alice', 'ver' => TokenVersion::current(5)]);
        $mgr->blacklist($token);

        $response = (new AdminAuthMiddleware())->process($this->request($token), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }

    public function test_token_with_stale_version_is_401(): void
    {
        Redis::del('admin_token_ver:77');
        $token = TokenManager::scope('admin')->generate(['admin_id' => 77, 'username' => 'bob', 'ver' => TokenVersion::current(77)]);
        $this->assertSame(200, $this->code((new AdminAuthMiddleware())->process($this->request($token), fn () => Api::success())));

        TokenVersion::bump(77);
        $response = (new AdminAuthMiddleware())->process($this->request($token), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
        $this->assertSame(lang('auth.token_expired'), json_decode((string) $response->rawBody(), true)['message']);
    }
}
