<?php

declare(strict_types=1);

namespace tests\Feature\Auth;

use app\middleware\ApiAuthMiddleware;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\response\Api;
use support\Db;
use tests\Support\ApiTestCase;
use Webman\Http\Request;

/**
 * M5a spec §4：C 端会员夹具 actingAsUser() 与 user scope 的 token 版本号。
 * 这里直接把中间件跑在构造出来的请求上——Task 5 之前还没有任何 /api 路由可打。
 */
final class ApiUserAuthTest extends ApiTestCase
{
    private function process(string $token): int
    {
        $request = new Request("GET /api/user/profile HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer {$token}\r\n\r\n");

        return (int) json_decode((string) (new ApiAuthMiddleware())->process($request, fn () => Api::success())->rawBody(), true)['code'];
    }

    public function test_acting_as_user_creates_a_real_row_and_a_usable_token(): void
    {
        $user = $this->actingAsUser(['nickname' => '测试会员']);

        $row = Db::table('users')->where('id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('测试会员', $row->nickname);
        $this->assertSame($user->mobile, $row->mobile);
        $this->assertSame(1, (int) $row->status);
        $this->assertTrue(password_verify($user->password, (string) $row->password), '夹具给的明文密码必须能登录');

        $payload = TokenManager::scope('user')->verify($user->token);
        $this->assertSame($user->id, $payload['user_id']);
        $this->assertSame(TokenVersion::current($user->id, 'user'), $payload['ver']);
        $this->assertSame(200, $this->process($user->token));
    }

    public function test_bumping_the_user_version_invalidates_the_issued_token(): void
    {
        $user = $this->actingAsUser();
        $this->assertSame(200, $this->process($user->token));

        TokenVersion::bump($user->id, 'user');

        $this->assertSame(401, $this->process($user->token), '禁用或改密后已签发的 token 立即失效');
    }

    public function test_each_fixture_user_gets_its_own_mobile(): void
    {
        // users.mobile 是唯一索引：夹具连开两个会员不能撞号
        $this->assertNotSame($this->actingAsUser()->mobile, $this->actingAsUser()->mobile);
    }
}
