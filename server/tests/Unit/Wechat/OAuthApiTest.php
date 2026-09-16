<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatUnavailableException;
use core\wechat\OAuthApi;
use core\wechat\WechatAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

/**
 * spec §3.3：公众号与开放平台共用的网页授权接口。userInfo 失败照常抛出，「尽力补全」由调用方（Task 5）捕获实现。
 */
final class OAuthApiTest extends TestCase
{
    use FakeWechatHttp;

    private const SECRET = 'SECRET-oauth';

    private const CODE = 'OAUTHCODE-003';

    private WechatAppConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new WechatAppConfig('wxoauth001', self::SECRET);
    }

    /** @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $responses */
    private function api(array $responses): OAuthApi
    {
        return new OAuthApi($this->wechatClient($responses));
    }

    public function test_authorize_url_matches_the_official_format(): void
    {
        $url = $this->api([])->authorizeUrl('wx123abc', 'https://m.example.com/mobile/pages/index?x=1&y=中文', 'snsapi_base');

        $this->assertSame(
            'https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx123abc'
            . '&redirect_uri=' . urlencode('https://m.example.com/mobile/pages/index?x=1&y=中文')
            . '&response_type=code&scope=snsapi_base&state=ydadmin#wechat_redirect',
            $url,
        );
        $this->assertSame([], $this->wechatRequests(), '拼授权链接不发请求');
    }

    public function test_authorize_url_accepts_snsapi_userinfo(): void
    {
        $this->assertStringContainsString('&scope=snsapi_userinfo&', $this->api([])->authorizeUrl('wx1', 'https://a.example.com/', 'snsapi_userinfo'));
    }

    /** @return iterable<string, array{string}> */
    public static function badScopes(): iterable
    {
        yield 'snsapi_login' => ['snsapi_login'];
        yield 'empty' => [''];
        yield 'injection' => ['snsapi_base&state=evil'];
    }

    #[DataProvider('badScopes')]
    public function test_authorize_url_rejects_other_scopes(string $scope): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->api([])->authorizeUrl('wx1', 'https://a.example.com/', $scope);
    }

    public function test_exchange_code_returns_openid_unionid_and_access_token(): void
    {
        $api = $this->api([self::wechatJson([
            'access_token'  => 'OAT-1',
            'expires_in'    => 7200,
            'refresh_token' => 'RT-1',
            'openid'        => 'o-oa',
            'scope'         => 'snsapi_base',
            'unionid'       => 'u-oa',
        ])]);

        $this->assertSame(['openid' => 'o-oa', 'unionid' => 'u-oa', 'access_token' => 'OAT-1'], $api->exchangeCode($this->config, self::CODE));

        $request = $this->wechatRequests()[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/sns/oauth2/access_token', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame([
            'appid'      => 'wxoauth001',
            'secret'     => self::SECRET,
            'code'       => self::CODE,
            'grant_type' => 'authorization_code',
        ], $query);
    }

    public function test_exchange_code_without_unionid_gives_null(): void
    {
        $result = $this->api([self::wechatJson(['access_token' => 'OAT', 'openid' => 'o-oa'])])->exchangeCode($this->config, self::CODE);

        $this->assertNull($result['unionid']);
    }

    public function test_exchange_code_rejected_code_is_an_api_exception_without_leaks(): void
    {
        try {
            $this->api([self::wechatJson(['errcode' => 40163, 'errmsg' => 'code been used'])])->exchangeCode($this->config, self::CODE);
            $this->fail('errcode 40163 必须抛出');
        } catch (WechatApiException $e) {
            $this->assertSame(40163, $e->getErrcode());
            $this->assertStringNotContainsString(self::CODE, $e->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function incompleteExchange(): iterable
    {
        yield 'no openid' => [['access_token' => 'OAT']];
        yield 'no access_token' => [['openid' => 'o-oa']];
        yield 'empty openid' => [['access_token' => 'OAT', 'openid' => '']];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('incompleteExchange')]
    public function test_exchange_code_incomplete_answer_is_unavailable(array $body): void
    {
        $this->expectException(WechatUnavailableException::class);

        $this->api([self::wechatJson($body)])->exchangeCode($this->config, self::CODE);
    }

    public function test_user_info_returns_nickname_and_avatar(): void
    {
        $api = $this->api([self::wechatJson(['openid' => 'o-web', 'nickname' => '张三', 'headimgurl' => 'https://thirdwx.qlogo.cn/a.jpg'])]);

        $this->assertSame(['nickname' => '张三', 'avatar' => 'https://thirdwx.qlogo.cn/a.jpg'], $api->userInfo('OAT-9', 'o-web'));

        $request = $this->wechatRequests()[0]['request'];
        $this->assertSame('/sns/userinfo', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['access_token' => 'OAT-9', 'openid' => 'o-web', 'lang' => 'zh_CN'], $query);
    }

    public function test_user_info_blank_fields_become_null(): void
    {
        $api = $this->api([self::wechatJson(['openid' => 'o-web', 'nickname' => '', 'headimgurl' => ''])]);

        $this->assertSame(['nickname' => null, 'avatar' => null], $api->userInfo('OAT-9', 'o-web'));
    }

    public function test_user_info_failure_propagates_for_the_caller_to_handle(): void
    {
        try {
            $this->api([self::wechatJson(['errcode' => 40003, 'errmsg' => 'invalid openid'])])->userInfo('OAT-SECRET-TOKEN', 'o-web');
            $this->fail('userInfo 失败必须抛出，由调用方决定是否回退');
        } catch (WechatApiException $e) {
            $this->assertSame(40003, $e->getErrcode());
            $this->assertStringNotContainsString('OAT-SECRET-TOKEN', $e->getMessage());
        }
    }
}
