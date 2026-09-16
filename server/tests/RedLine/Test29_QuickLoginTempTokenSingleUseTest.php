<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;

/**
 * 红线（M6a spec §4.4、§4.5、设计决定 9）：quick-login 发出的 temp_token 只能用一次。它是「这个小程序 openid
 * 已通过 code2Session 验证」的临时凭证：能重放，截获者就能在 5 分钟内拿同一个 openid 反复绑手机、反复签发 token。
 * 第二次使用必须在调用微信之前就失败（不能再解一次手机号）；编造的 token 同样不触网。
 */
final class Test29_QuickLoginTempTokenSingleUseTest extends ApiTestCase
{
    use FakeWechatHttp;

    private string $appId = '';

    protected function setUp(): void
    {
        parent::setUp();
        // 随机 appid：AccessTokenProvider 按 appid 缓存 token，固定 appid 会命中别的用例留下的缓存、少一次 cgi-bin/token 调用
        $this->appId = 'wx29' . bin2hex(random_bytes(6));
        $this->setConfig('wechat_mini_app_id', $this->appId);
        $this->setConfig('wechat_mini_app_secret', 'secret29' . bin2hex(random_bytes(8)));
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreWechatHttp();
        } finally {
            parent::tearDown();
        }
    }

    public function test_temp_token_cannot_be_replayed(): void
    {
        $openid = 'MINI29' . bin2hex(random_bytes(8));
        $mobile = '139' . sprintf('%08d', random_int(0, 99_999_999));
        $this->fakeWechatHttp([
            self::wechatJson(['openid' => $openid, 'session_key' => 'SK29' . bin2hex(random_bytes(8))]),
            self::wechatJson(['access_token' => 'TK29' . bin2hex(random_bytes(8)), 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 0, 'errmsg' => 'ok', 'phone_info' => ['phoneNumber' => $mobile, 'purePhoneNumber' => $mobile, 'countryCode' => '86']]),
        ]);

        $quick = $this->post('/api/auth/wechat-quick-login', ['code' => 'code29' . bin2hex(random_bytes(4))])->assertOk();
        $quickData = (array) $quick->data();
        $this->assertSame('need_bindphone', $quickData['status'] ?? null, '前置条件：新 openid 必须走绑手机');
        $tempToken = (string) ($quickData['temp_token'] ?? '');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $tempToken);

        $first = $this->post('/api/auth/wechat-bindphone', ['temp_token' => $tempToken, 'phone_code' => 'pc29a'])->assertOk();
        $firstData = (array) $first->data();
        $this->assertSame('logged_in', $firstData['status'] ?? null, '正向对照：第一次使用必须成功');
        $userId = (int) (((array) ($firstData['user_info'] ?? []))['id'] ?? 0);
        $this->assertGreaterThan(0, $userId);
        $this->trackUser($userId);
        $this->assertSame($openid, Db::table('users')->where('id', $userId)->value('mini_openid'));
        $callsAfterFirst = count($this->wechatRequests());
        $this->assertSame(3, $callsAfterFirst, 'code2Session + token + 手机号，各一次');

        $second = $this->post('/api/auth/wechat-bindphone', ['temp_token' => $tempToken, 'phone_code' => 'pc29b']);

        $second->assertCode(400);
        $this->assertSame(lang('wechat.quick_expired'), $second->message());
        $this->assertArrayNotHasKey('token', (array) $second->data(), '重放不能再签发 token');
        $this->assertSame($callsAfterFirst, count($this->wechatRequests()), '重放必须在调用微信之前失败');
        $this->assertSame(1, Db::table('users')->where('mini_openid', $openid)->count(), '重放不能多注册用户');
    }

    public function test_invented_temp_token_is_rejected_without_calling_wechat(): void
    {
        $this->fakeWechatHttp([]);

        $response = $this->post('/api/auth/wechat-bindphone', ['temp_token' => bin2hex(random_bytes(16)), 'phone_code' => 'pc29c']);

        $response->assertCode(400);
        $this->assertSame(lang('wechat.quick_expired'), $response->message());
        $this->assertSame([], $this->wechatRequests());
    }
}
