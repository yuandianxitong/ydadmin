<?php

declare(strict_types=1);

namespace tests\Feature\User;

use app\service\user\UserAuthService;
use app\service\user\UserSessionIssuer;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\exception\NotFoundException;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

/**
 * M6a 设计决定 8：UserSessionIssuer 是从 M5a UserAuthService::loginSuccess() 逐字搬出的共享签发，
 * 微信登录与账号密码 / 短信登录共用同一份「更新最后登录 + 签发带 ver 的 user token + 四字段 user_info」。
 */
final class UserSessionIssuerTest extends ApiTestCase
{
    public function test_issue_updates_last_login_and_returns_a_token_with_ver(): void
    {
        $user = $this->actingAsUser(['nickname' => '签发测试', 'avatar' => 'https://example.com/a.png']);
        TokenVersion::bump($user->id, 'user');
        $expectedVer = TokenVersion::current($user->id, 'user');

        $result = Container::get(UserSessionIssuer::class)->issue($user->id, '203.0.113.7');

        $this->assertSame(['token', 'user_info'], array_keys($result));
        $this->assertSame(['id', 'nickname', 'avatar', 'mobile'], array_keys($result['user_info']));
        $this->assertSame($user->id, $result['user_info']['id']);
        $this->assertSame('签发测试', $result['user_info']['nickname']);
        $this->assertSame('https://example.com/a.png', $result['user_info']['avatar']);
        $this->assertSame($user->mobile, $result['user_info']['mobile']);

        $claims = TokenManager::scope('user')->verify($result['token']);
        $this->assertSame($user->id, (int) $claims['user_id']);
        $this->assertSame($expectedVer, (int) $claims['ver']);

        $row = Db::table('users')->where('id', $user->id)->first();
        $this->assertSame(1, (int) $row->login_count);
        $this->assertSame('203.0.113.7', $row->last_login_ip);
        $this->assertNotNull($row->last_login_time);
    }

    public function test_issue_returns_null_avatar_and_mobile_for_a_wechat_user_without_them(): void
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('users')->insertGetId([
            'nickname'    => '微信用户',
            'mini_openid' => 'o_' . bin2hex(random_bytes(6)),
            'status'      => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $this->trackUser($id);

        $result = Container::get(UserSessionIssuer::class)->issue($id, '127.0.0.1');

        $this->assertSame(['id' => $id, 'nickname' => '微信用户', 'avatar' => null, 'mobile' => null], $result['user_info']);
    }

    public function test_issue_throws_not_found_for_a_missing_user(): void
    {
        $this->expectException(NotFoundException::class);

        Container::get(UserSessionIssuer::class)->issue(0, '127.0.0.1');
    }

    public function test_user_auth_service_no_longer_keeps_its_own_copy_of_the_issue_logic(): void
    {
        $this->assertFalse(method_exists(UserAuthService::class, 'loginSuccess'), 'loginSuccess 已抽到 UserSessionIssuer，不得留两份');

        $property = new \ReflectionProperty(UserAuthService::class, 'userSessionIssuer');
        $this->assertSame(UserSessionIssuer::class, (string) $property->getType());
    }
}
