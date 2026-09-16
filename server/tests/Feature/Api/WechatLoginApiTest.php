<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;
use tests\Support\Wechat\WechatUserFixtures;

/** M6a spec §4.2、§4.3、§4.10：两个登录端点的 HTTP 契约。 */
final class WechatLoginApiTest extends ApiTestCase
{
    use FakeWechatHttp;
    use WechatUserFixtures;

    protected function tearDown(): void
    {
        try {
            $this->restoreWechatHttp();
        } finally {
            $this->cleanupWechatFixtures();
            parent::tearDown();
        }
    }

    public function test_wechat_login_is_public_and_returns_token_with_user_info(): void
    {
        $this->configureWechatApps();
        $this->fakeWechatHttp([self::wechatJson(['openid' => $this->fixtureOpenid(), 'session_key' => 'SK'])]);

        $response = $this->post('/api/auth/wechat-login', ['code' => 'code-api-1']);

        $response->assertOk();
        $data = $response->data();
        $this->assertSame(['token', 'user_info'], array_keys($data));
        $this->assertSame(['id', 'nickname', 'avatar', 'mobile'], array_keys($data['user_info']));
        $this->assertStringNotContainsString('session_key', $response->body());
    }

    public function test_wechat_web_login_returns_token_with_user_info(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'AT', 'expires_in' => 7200, 'openid' => $openid]),
            self::wechatJson(['openid' => $openid, 'nickname' => '李四', 'headimgurl' => '']),
        ]);

        $response = $this->post('/api/auth/wechat-web-login', ['code' => 'code-api-2']);

        $response->assertOk();
        $this->assertSame('李四', $response->data()['user_info']['nickname']);
        $this->assertNull($response->data()['user_info']['avatar']);
    }

    public function test_missing_code_is_422_on_both_endpoints(): void
    {
        $this->fakeWechatHttp([]);

        foreach (['/api/auth/wechat-login', '/api/auth/wechat-web-login'] as $uri) {
            $response = $this->post($uri, []);
            $response->assertCode(422);
            $this->assertArrayHasKey('code', $response->data()['errors'] ?? [], $uri);
        }
        $this->assertSame([], $this->wechatRequests());
    }

    public function test_unconfigured_wechat_returns_business_error(): void
    {
        $this->setConfig('wechat_mini_app_id', '');
        $this->setConfig('wechat_open_app_id', '');
        $this->fakeWechatHttp([]);

        foreach (['/api/auth/wechat-login', '/api/auth/wechat-web-login'] as $uri) {
            $response = $this->post($uri, ['code' => 'code-api-3']);
            $response->assertCode(400);
            $this->assertSame('微信登录未配置', $response->message(), $uri);
        }
        $this->assertSame([], $this->wechatRequests());
    }

    public function test_rejected_code_does_not_echo_wechat_errmsg(): void
    {
        $this->configureWechatApps();
        $this->fakeWechatHttp([self::wechatJson(['errcode' => 40163, 'errmsg' => 'code been used, rid: RAW-ERRMSG'])]);

        $response = $this->post('/api/auth/wechat-login', ['code' => 'code-api-4']);

        $response->assertCode(400);
        $this->assertSame('微信授权失败，请重试', $response->message());
        $this->assertStringNotContainsString('RAW-ERRMSG', $response->body());
        $this->assertStringNotContainsString('40163', $response->body());
    }
}
