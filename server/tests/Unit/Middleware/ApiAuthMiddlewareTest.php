<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\ApiAuthMiddleware;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\context\RequestContext;
use core\response\Api;
use support\Redis;
use tests\TestCase;
use Webman\Http\Request;

final class ApiAuthMiddlewareTest extends TestCase
{
    /** 本用例用到的两个会员 id 与一个同号管理员 id 的版本号键 */
    private const KEYS = ['user_token_ver:42', 'user_token_ver:7', 'admin_token_ver:42'];

    protected function setUp(): void
    {
        parent::setUp();
        TokenManager::flushInstances();
        Redis::del(...self::KEYS);
    }

    protected function tearDown(): void
    {
        Redis::del(...self::KEYS);
        parent::tearDown();
    }

    private function request(?string $token): Request
    {
        $auth = $token !== null ? "Authorization: Bearer {$token}\r\n" : '';
        return new Request("GET /api/demo HTTP/1.1\r\nHost: localhost\r\n{$auth}\r\n");
    }

    public function test_valid_user_token_sets_user_id_but_not_acting_user(): void
    {
        $token = TokenManager::scope('user')->generate(['user_id' => 42, 'ver' => TokenVersion::current(42, 'user')]);
        $request = $this->request($token);
        $response = (new ApiAuthMiddleware())->process($request, fn () => Api::success());

        $this->assertSame(200, json_decode((string) $response->rawBody(), true)['code']);
        $this->assertSame(42, $request->userId);
        $this->assertSame(0, RequestContext::actingUser(), 'acting user 只代表管理员（M1 数据权限依赖这一点）');
    }

    public function test_stale_token_version_is_401(): void
    {
        $token = TokenManager::scope('user')->generate(['user_id' => 7, 'ver' => TokenVersion::current(7, 'user')]);
        // 管理端把该会员改成禁用、或会员自己改了密码
        TokenVersion::bump(7, 'user');

        $body = json_decode((string) (new ApiAuthMiddleware())->process($this->request($token), fn () => Api::success())->rawBody(), true);

        $this->assertSame(401, $body['code']);
        $this->assertSame(lang('auth.token_expired'), $body['message']);
    }

    public function test_token_without_a_version_claim_is_401(): void
    {
        // 不带 ver 的 token 按 0 比对，永远对不上（与 AdminAuthMiddleware 同样 fail closed）
        $token = TokenManager::scope('user')->generate(['user_id' => 42]);

        $this->assertSame(401, json_decode((string) (new ApiAuthMiddleware())->process($this->request($token), fn () => Api::success())->rawBody(), true)['code']);
    }

    public function test_user_version_is_isolated_from_the_admin_version_of_the_same_id(): void
    {
        $token = TokenManager::scope('user')->generate(['user_id' => 42, 'ver' => TokenVersion::current(42, 'user')]);
        // 同 id 的管理员被禁用：C 端 token 不受影响
        TokenVersion::bump(42);

        $this->assertSame(200, json_decode((string) (new ApiAuthMiddleware())->process($this->request($token), fn () => Api::success())->rawBody(), true)['code']);
    }

    public function test_admin_token_and_missing_token_are_401(): void
    {
        foreach ([TokenManager::scope('admin')->generate(['admin_id' => 1]), null] as $token) {
            $response = (new ApiAuthMiddleware())->process($this->request($token), fn () => Api::success());
            $this->assertSame(401, json_decode((string) $response->rawBody(), true)['code']);
        }
    }
}
