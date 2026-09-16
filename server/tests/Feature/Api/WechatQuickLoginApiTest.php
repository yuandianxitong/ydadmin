<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use app\service\wechat\WechatAuthService;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;
use tests\Support\Wechat\WechatUserFixtures;

/** M6a spec §4.4、§4.5：快捷登录与绑手机号的 HTTP 契约（uniapp modules/login 的消费形状）。 */
final class WechatQuickLoginApiTest extends ApiTestCase
{
    use FakeWechatHttp;
    use WechatUserFixtures;

    /** @var list<string> */
    private array $tempTokens = [];

    protected function tearDown(): void
    {
        try {
            $this->restoreWechatHttp();
            foreach ($this->tempTokens as $token) {
                Redis::del(WechatAuthService::QUICK_KEY_PREFIX . $token);
            }
        } finally {
            $this->cleanupWechatFixtures();
            parent::tearDown();
        }
    }

    public function test_quick_login_then_bindphone_over_http(): void
    {
        $this->configureWechatApps();
        $mobile = $this->fixtureMobile();
        $this->fakeWechatHttp([
            self::wechatJson(['openid' => $this->fixtureOpenid(), 'session_key' => 'SK']),
            self::wechatJson(['access_token' => 'AT', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 0, 'phone_info' => ['purePhoneNumber' => $mobile, 'phoneNumber' => $mobile, 'countryCode' => '86']]),
        ]);

        $quick = $this->post('/api/auth/wechat-quick-login', ['code' => 'code-http-1']);
        $quick->assertOk();
        $this->assertSame('need_bindphone', $quick->data()['status']);
        $temp = (string) $quick->data()['temp_token'];
        $this->tempTokens[] = $temp;

        $bind = $this->post('/api/auth/wechat-bindphone', ['temp_token' => $temp, 'phone_code' => 'pc-http-1']);
        $bind->assertOk();
        $this->assertSame('logged_in', $bind->data()['status']);
        $this->assertNotSame('', (string) $bind->data()['token']);
        $this->assertSame($mobile, $bind->data()['user_info']['mobile']);
    }

    public function test_bindphone_validation_and_expired_token(): void
    {
        $this->fakeWechatHttp([]);

        $this->post('/api/auth/wechat-bindphone', ['temp_token' => 'short', 'phone_code' => 'x'])->assertCode(422);
        $this->post('/api/auth/wechat-bindphone', ['temp_token' => bin2hex(random_bytes(16))])->assertCode(422);

        $expired = $this->post('/api/auth/wechat-bindphone', ['temp_token' => bin2hex(random_bytes(16)), 'phone_code' => 'x']);
        $expired->assertCode(400);
        $this->assertSame('登录已过期，请重新授权', $expired->message());
        $this->assertSame([], $this->wechatRequests());
    }

    public function test_quick_login_missing_code_is_422(): void
    {
        $this->fakeWechatHttp([]);

        $response = $this->post('/api/auth/wechat-quick-login', []);

        $response->assertCode(422);
        $this->assertArrayHasKey('code', $response->data()['errors'] ?? []);
    }
}
