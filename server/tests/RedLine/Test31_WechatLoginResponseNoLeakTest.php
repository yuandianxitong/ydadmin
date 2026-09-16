<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;
use tests\Support\TestResponse;
use tests\Support\Wechat\FakeWechatHttp;

/**
 * 红线（M6a spec §2 约束、§4）：五个微信登录端点的响应里不能出现微信给后端的秘密与内部列——session_key
 * （能解出小程序加密数据）、网页/公众号 access_token 与 refresh_token（能以用户身份调 sns 接口）、手机号接口的
 * 原始 phone_info，以及用户行里的 password、mini_openid、oa_openid；user_info 里也不能带 openid / unionid。
 *
 * 例外（契约字段，放行）：wechat-h5-login 顶层的 openid、unionid（uniapp 存本地）；user_info.mobile。
 * 除了按键名递归检查，还按**值**检查：假微信返回的每个秘密串都不能出现在响应体原文里（防止换了个键名漏出去）。
 */
final class Test31_WechatLoginResponseNoLeakTest extends ApiTestCase
{
    use FakeWechatHttp;

    private const FORBIDDEN_KEYS = ['session_key', 'password', 'access_token', 'refresh_token', 'phone_info', 'purePhoneNumber', 'phoneNumber', 'watermark', 'mini_openid', 'oa_openid'];
    private const FORBIDDEN_IN_USER_INFO = ['openid', 'unionid', 'oa_openid', 'mini_openid', 'password', 'session_key'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('site_url', 'https://m31.example.com');
        foreach (['mini', 'official', 'open'] as $side) {
            $this->setConfig("wechat_{$side}_app_id", 'wx31' . bin2hex(random_bytes(6)));
            $this->setConfig("wechat_{$side}_app_secret", 'secret31' . bin2hex(random_bytes(8)));
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreWechatHttp();
        } finally {
            parent::tearDown();
        }
    }

    public function test_mini_login_and_quick_login_do_not_leak_session_key(): void
    {
        $sessionKey = 'SK31SECRET' . bin2hex(random_bytes(8));
        $this->fakeWechatHttp([
            self::wechatJson(['openid' => 'MINI31A' . bin2hex(random_bytes(6)), 'unionid' => 'UN31A' . bin2hex(random_bytes(6)), 'session_key' => $sessionKey]),
            self::wechatJson(['openid' => 'MINI31B' . bin2hex(random_bytes(6)), 'session_key' => $sessionKey]),
        ]);

        $mini = $this->post('/api/auth/wechat-login', ['code' => 'code31a'])->assertOk();
        $this->trackLoggedIn($mini);
        $quick = $this->post('/api/auth/wechat-quick-login', ['code' => 'code31b'])->assertOk();

        foreach (['wechat-login' => $mini, 'wechat-quick-login' => $quick] as $endpoint => $response) {
            $this->assertNoLeak($endpoint, $response, [$sessionKey]);
        }
    }

    public function test_web_login_does_not_leak_oauth_tokens(): void
    {
        $accessToken = 'AT31SECRET' . bin2hex(random_bytes(8));
        $refreshToken = 'RT31SECRET' . bin2hex(random_bytes(8));
        $openid = 'WEB31' . bin2hex(random_bytes(6));
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => $accessToken, 'expires_in' => 7200, 'refresh_token' => $refreshToken, 'openid' => $openid, 'scope' => 'snsapi_login', 'unionid' => 'UN31W' . bin2hex(random_bytes(6))]),
            self::wechatJson(['openid' => $openid, 'nickname' => '网页31', 'headimgurl' => 'https://thirdwx.qlogo.cn/31.png', 'privilege' => []]),
        ]);

        $response = $this->post('/api/auth/wechat-web-login', ['code' => 'code31w'])->assertOk();
        $this->trackLoggedIn($response);

        $this->assertNoLeak('wechat-web-login', $response, [$accessToken, $refreshToken, $openid]);
    }

    public function test_h5_login_does_not_leak_oauth_tokens(): void
    {
        $accessToken = 'AT31H5SECRET' . bin2hex(random_bytes(8));
        $refreshToken = 'RT31H5SECRET' . bin2hex(random_bytes(8));
        $openid = 'OA31' . bin2hex(random_bytes(6));
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => $accessToken, 'expires_in' => 7200, 'refresh_token' => $refreshToken, 'openid' => $openid, 'scope' => 'snsapi_base']),
        ]);

        $response = $this->post('/api/auth/wechat-h5-login', ['code' => 'code31h'])->assertOk();

        // 顶层 openid 是契约字段，所以这里不按值禁 openid
        $this->assertNoLeak('wechat-h5-login', $response, [$accessToken, $refreshToken]);
    }

    public function test_bindphone_does_not_leak_raw_phone_payload(): void
    {
        $sessionKey = 'SK31BP' . bin2hex(random_bytes(8));
        $token = 'TK31SECRET' . bin2hex(random_bytes(8));
        $mobile = '137' . sprintf('%08d', random_int(0, 99_999_999));
        $this->fakeWechatHttp([
            self::wechatJson(['openid' => 'MINI31P' . bin2hex(random_bytes(6)), 'session_key' => $sessionKey]),
            self::wechatJson(['access_token' => $token, 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 0, 'errmsg' => 'ok', 'phone_info' => ['phoneNumber' => '+86' . $mobile, 'purePhoneNumber' => $mobile, 'countryCode' => '86', 'watermark' => ['appid' => 'wx31wm', 'timestamp' => time()]]]),
        ]);
        $tempToken = (string) (((array) $this->post('/api/auth/wechat-quick-login', ['code' => 'code31p'])->assertOk()->data())['temp_token'] ?? '');

        $response = $this->post('/api/auth/wechat-bindphone', ['temp_token' => $tempToken, 'phone_code' => 'pc31SECRET'])->assertOk();
        $this->trackLoggedIn($response);

        $this->assertNoLeak('wechat-bindphone', $response, [$sessionKey, $token, '+86' . $mobile, 'pc31SECRET', 'wx31wm']);
        $this->assertSame($mobile, ((array) (((array) $response->data())['user_info'] ?? []))['mobile'] ?? null, '正向对照：user_info.mobile 是契约字段，应为绑定的手机号');
    }

    /** @param list<string> $secretValues */
    private function assertNoLeak(string $endpoint, TestResponse $response, array $secretValues): void
    {
        $data = (array) $response->data();
        $this->assertNotSame([], $data, "{$endpoint}：前置条件——响应要有数据，否则下面的断言是空转");
        $this->assertSame([], $this->forbiddenKeysIn($data, self::FORBIDDEN_KEYS), "{$endpoint}：响应里出现了禁止的键");
        if (isset($data['user_info'])) {
            $this->assertSame([], $this->forbiddenKeysIn((array) $data['user_info'], self::FORBIDDEN_IN_USER_INFO), "{$endpoint}：user_info 里出现了禁止的键");
        }
        foreach ($secretValues as $secret) {
            $this->assertStringNotContainsString($secret, $response->body(), "{$endpoint}：响应体原文里出现了微信返回的秘密值");
        }
    }

    /**
     * @param array<mixed> $node
     * @param list<string> $forbidden
     * @return list<string> 命中的键路径
     */
    private function forbiddenKeysIn(array $node, array $forbidden, string $path = ''): array
    {
        $hits = [];
        foreach ($node as $key => $value) {
            $current = $path === '' ? (string) $key : "{$path}.{$key}";
            if (is_string($key) && in_array($key, $forbidden, true)) {
                $hits[] = $current;
            }
            if (is_array($value)) {
                $hits = [...$hits, ...$this->forbiddenKeysIn($value, $forbidden, $current)];
            }
        }

        return $hits;
    }

    private function trackLoggedIn(TestResponse $response): void
    {
        $id = (int) (((array) (((array) $response->data())['user_info'] ?? []))['id'] ?? 0);
        if ($id > 0) {
            $this->trackUser($id);
        }
    }
}
