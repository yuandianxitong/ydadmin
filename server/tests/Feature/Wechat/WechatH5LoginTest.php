<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\service\wechat\WechatAuthService;
use app\service\wechat\WechatOaBindCookie;
use core\exception\BusinessException;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;
use tests\Support\Wechat\FakeWechatHttp;

/** M6a spec §4.6、§4.8：公众号静默登录与 openid 绑定（服务层 + 接口）。 */
final class WechatH5LoginTest extends ApiTestCase
{
    use ConfigOverride;
    use FakeWechatHttp;

    private const OA_APP_ID = 'wx0a1b2c3d4e5f6a7b';

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideConfig('auth.jwt.user.key', 'h5-login-test-secret-0123456789ab');
        $this->setConfig('wechat_official_app_id', self::OA_APP_ID);
        $this->setConfig('wechat_official_app_secret', 'oa-secret-for-tests');
        $this->setConfig('site_url', 'http://shop.example.com');
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreWechatHttp();
            $this->restoreConfig();
        } finally {
            parent::tearDown();
        }
    }

    private function service(): WechatAuthService
    {
        return Container::get(WechatAuthService::class);
    }

    private static function openid(): string
    {
        return 'oOA' . bin2hex(random_bytes(10));
    }

    /** @return array<string, mixed> 微信 sns/oauth2/access_token 的真实应答形状 */
    private static function exchange(string $openid, ?string $unionid = null): array
    {
        return array_filter([
            'access_token'    => 'OA_ACCESS_TOKEN_' . bin2hex(random_bytes(4)),
            'expires_in'      => 7200,
            'refresh_token'   => 'REFRESH',
            'openid'          => $openid,
            'scope'           => 'snsapi_base',
            'is_snapshotuser' => 0,
            'unionid'         => $unionid,
        ], static fn (mixed $v): bool => $v !== null);
    }

    public function test_bound_openid_logs_in_without_cookie(): void
    {
        $openid = self::openid();
        $user = $this->actingAsUser(['oa_openid' => $openid, 'unionid' => null]);
        $this->fakeWechatHttp([self::wechatJson(self::exchange($openid))]);

        $response = $this->post('/api/auth/wechat-h5-login', ['code' => 'CODE-H5-1']);

        $response->assertOk();
        $data = $response->data();
        $this->assertSame('logged_in', $data['status']);
        $this->assertSame($openid, $data['openid']);
        $this->assertNull($data['unionid']);
        $this->assertNotSame('', (string) $data['token']);
        $this->assertSame($user->id, $data['user_info']['id']);
        $this->assertNull($response->header('Set-Cookie'), '已绑定用户直接登录，不需要绑定证明');

        $sent = $this->wechatRequests()[0]['request'];
        $this->assertSame('/sns/oauth2/access_token', $sent->getUri()->getPath());
        parse_str($sent->getUri()->getQuery(), $query);
        $this->assertSame(self::OA_APP_ID, $query['appid']);
        $this->assertSame('CODE-H5-1', $query['code']);
    }

    public function test_unionid_match_binds_empty_oa_openid_and_logs_in(): void
    {
        $openid = self::openid();
        $unionid = 'oUN' . bin2hex(random_bytes(10));
        $user = $this->actingAsUser(['unionid' => $unionid]);
        $this->fakeWechatHttp([self::wechatJson(self::exchange($openid, $unionid))]);

        $result = $this->service()->h5Login('CODE-H5-2', '203.0.113.5');

        $this->assertSame('logged_in', $result['status']);
        $this->assertSame($unionid, $result['unionid']);
        $this->assertSame($openid, Db::table('users')->where('id', $user->id)->value('oa_openid'));
    }

    public function test_unbound_openid_returns_need_login_and_sets_bind_cookie_without_registering(): void
    {
        $openid = self::openid();
        $usersBefore = Db::table('users')->count();
        $this->fakeWechatHttp([self::wechatJson(self::exchange($openid))]);

        $response = $this->post('/api/auth/wechat-h5-login', ['code' => 'CODE-H5-3']);

        $response->assertOk();
        $this->assertSame(['status' => 'need_login', 'openid' => $openid, 'unionid' => null], $response->data());
        $this->assertSame($usersBefore, Db::table('users')->count(), 'h5 静默登录不注册');

        $header = (string) $response->header('Set-Cookie');
        $this->assertStringStartsWith(WechatOaBindCookie::NAME . '=', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $value = substr(explode(';', $header)[0], strlen(WechatOaBindCookie::NAME) + 1);
        $this->assertSame($openid, Container::get(WechatOaBindCookie::class)->verify($value));
    }

    public function test_disabled_user_is_refused(): void
    {
        $openid = self::openid();
        $this->actingAsUser(['oa_openid' => $openid, 'status' => 0]);
        $this->fakeWechatHttp([self::wechatJson(self::exchange($openid))]);

        $response = $this->post('/api/auth/wechat-h5-login', ['code' => 'CODE-H5-4']);

        $response->assertCode(400);
        $this->assertSame(lang('auth.account_disabled'), $response->message());
        $this->assertNull($response->header('Set-Cookie'));
    }

    public function test_not_configured_and_rejected_code_and_network_failure(): void
    {
        $this->setConfig('wechat_official_app_secret', '');
        $this->post('/api/auth/wechat-h5-login', ['code' => 'X'])->assertCode(400);
        $this->assertSame(lang('wechat.not_configured'), $this->post('/api/auth/wechat-h5-login', ['code' => 'X'])->message());

        $this->setConfig('wechat_official_app_secret', 'oa-secret-for-tests');
        $this->fakeWechatHttp([self::wechatJson(['errcode' => 40029, 'errmsg' => 'invalid code'])]);
        $rejected = $this->post('/api/auth/wechat-h5-login', ['code' => 'BAD']);
        $rejected->assertCode(400);
        $this->assertSame(lang('wechat.auth_failed'), $rejected->message());
        $this->assertStringNotContainsString('40029', $rejected->body(), 'errcode 只进日志');

        $this->fakeWechatHttp([self::wechatJson(['boom' => true], 502)]);
        $down = $this->post('/api/auth/wechat-h5-login', ['code' => 'ANY']);
        $down->assertCode(400);
        $this->assertSame(lang('wechat.unavailable'), $down->message());
    }

    public function test_need_login_with_empty_user_jwt_key_is_not_configured_instead_of_500(): void
    {
        $this->overrideConfig('auth.jwt.user.key', '');
        $this->fakeWechatHttp([self::wechatJson(self::exchange(self::openid()))]);

        $response = $this->post('/api/auth/wechat-h5-login', ['code' => 'CODE-H5-NOKEY']);

        $this->assertSame(200, $response->status());
        $response->assertCode(400);
        $this->assertSame(lang('wechat.not_configured'), $response->message());
        $this->assertNull($response->header('Set-Cookie'), '签不出证明就不下发 cookie');
    }

    public function test_code_is_required(): void
    {
        $this->post('/api/auth/wechat-h5-login', [])->assertCode(422);
    }

    // ---------------------------------------------------------------- bindOaOpenid（服务层分支）

    public function test_bind_without_valid_proof_is_refused(): void
    {
        $user = $this->actingAsUser();

        foreach ([null, 'oOA_someone_else'] as $proof) {
            try {
                $this->service()->bindOaOpenid($user->id, 'oOA_claimed_openid', $proof);
                $this->fail('无证明或证明与请求 openid 不符必须拒绝');
            } catch (BusinessException $e) {
                $this->assertSame(lang('wechat.oa_bind_invalid'), $e->getMessage());
            }
        }
        $this->assertNull(Db::table('users')->where('id', $user->id)->value('oa_openid'));
    }

    public function test_bind_is_idempotent_for_same_openid(): void
    {
        $openid = self::openid();
        $user = $this->actingAsUser(['oa_openid' => $openid]);

        $this->service()->bindOaOpenid($user->id, $openid, $openid);

        $this->assertSame($openid, Db::table('users')->where('id', $user->id)->value('oa_openid'));
    }

    public function test_bind_never_overwrites_current_users_other_openid(): void
    {
        $user = $this->actingAsUser(['oa_openid' => 'oOA_existing_' . bin2hex(random_bytes(4))]);
        $openid = self::openid();

        try {
            $this->service()->bindOaOpenid($user->id, $openid, $openid);
            $this->fail('已绑定其他 openid 的账号不得覆盖');
        } catch (BusinessException $e) {
            $this->assertSame(lang('wechat.account_bound_other_wechat'), $e->getMessage());
        }
    }

    public function test_bind_refuses_openid_owned_by_another_user(): void
    {
        $openid = self::openid();
        $this->actingAsUser(['oa_openid' => $openid]);
        $me = $this->actingAsUser();

        try {
            $this->service()->bindOaOpenid($me->id, $openid, $openid);
            $this->fail('openid 已被他人占用必须拒绝');
        } catch (BusinessException $e) {
            $this->assertSame(lang('wechat.wechat_bound_other_account'), $e->getMessage());
        }
        $this->assertNull(Db::table('users')->where('id', $me->id)->value('oa_openid'));
    }

    public function test_bind_writes_openid_when_proof_matches(): void
    {
        $user = $this->actingAsUser();
        $openid = self::openid();

        $this->service()->bindOaOpenid($user->id, $openid, $openid);

        $this->assertSame($openid, Db::table('users')->where('id', $user->id)->value('oa_openid'));
    }

    public function test_bind_under_held_lock_is_too_frequent(): void
    {
        $user = $this->actingAsUser();
        $openid = self::openid();
        $lockKey = "wechat:login_lock:oa_openid:{$openid}";
        Redis::set($lockKey, 'held-by-another-worker', 'EX', 5);

        try {
            $this->service()->bindOaOpenid($user->id, $openid, $openid);
            $this->fail('锁被占用时必须报 429');
        } catch (BusinessException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(lang('wechat.too_frequent'), $e->getMessage());
        } finally {
            Redis::del($lockKey);
        }
        $this->assertNull(Db::table('users')->where('id', $user->id)->value('oa_openid'));
    }
}
