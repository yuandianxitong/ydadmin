<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\service\wechat\WechatOaBindCookie;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

/** M6a spec §4.8：绑定接口只认 cookie 里的 openid。 */
final class BindOaOpenidApiTest extends ApiTestCase
{
    use ConfigOverride;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideConfig('auth.jwt.user.key', 'bind-api-test-secret-0123456789ab');
        $this->setConfig('site_url', 'http://shop.example.com');
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreConfig();
        } finally {
            parent::tearDown();
        }
    }

    private function proof(string $openid): string
    {
        return WechatOaBindCookie::NAME . '=' . Container::get(WechatOaBindCookie::class)->issue($openid);
    }

    public function test_requires_login(): void
    {
        $this->post('/api/user/bind-oa-openid', ['oa_openid' => 'oOA_x'])->assertCode(401);
    }

    public function test_without_cookie_is_refused_and_nothing_written(): void
    {
        $user = $this->actingAsUser();

        $response = $this->post('/api/user/bind-oa-openid', ['oa_openid' => 'oOA_victim_openid'], $user->token);

        $response->assertCode(400);
        $this->assertSame(lang('wechat.oa_bind_invalid'), $response->message());
        $this->assertNull(Db::table('users')->where('id', $user->id)->value('oa_openid'));
    }

    public function test_cookie_for_another_openid_is_refused(): void
    {
        $user = $this->actingAsUser();

        $response = $this->post('/api/user/bind-oa-openid', ['oa_openid' => 'oOA_victim_openid'], $user->token, [
            'Cookie' => $this->proof('oOA_my_own_openid'),
        ]);

        $response->assertCode(400);
        $this->assertNull(Db::table('users')->where('id', $user->id)->value('oa_openid'));
    }

    public function test_valid_cookie_binds_and_clears_cookie(): void
    {
        $user = $this->actingAsUser();
        $openid = 'oOA' . bin2hex(random_bytes(10));

        $response = $this->post('/api/user/bind-oa-openid', ['oa_openid' => $openid], $user->token, [
            'Cookie' => 'other=1; ' . $this->proof($openid),
        ]);

        $response->assertOk();
        $this->assertSame([], $response->data());
        $this->assertSame($openid, Db::table('users')->where('id', $user->id)->value('oa_openid'));
        $header = (string) $response->header('Set-Cookie');
        $this->assertStringStartsWith(WechatOaBindCookie::NAME . '=;', $header);
        $this->assertStringContainsString('Max-Age=0', $header);
    }

    public function test_oa_openid_is_required(): void
    {
        $user = $this->actingAsUser();

        $this->post('/api/user/bind-oa-openid', [], $user->token)->assertCode(422);
    }
}
