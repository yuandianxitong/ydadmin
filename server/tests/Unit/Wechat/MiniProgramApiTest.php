<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use core\wechat\AccessTokenProvider;
use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatUnavailableException;
use core\wechat\MiniProgramApi;
use core\wechat\WechatAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use support\Redis;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

/**
 * spec §3.3：code2Session 丢弃 session_key；getPhoneNumber 带 access_token，
 * 遇 40001 / 40014 / 42001 invalidate 后重试一次，第二次仍失败就抛出。
 */
final class MiniProgramApiTest extends TestCase
{
    use FakeWechatHttp;

    private const SECRET = 'SECRET-mini-app';

    private const CODE = 'JSCODE-mini-001';

    private const PHONE_CODE = 'PHONECODE-mini-002';

    private WechatAppConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new WechatAppConfig('wxmini' . bin2hex(random_bytes(6)), self::SECRET);
    }

    protected function tearDown(): void
    {
        try {
            Redis::del('wechat:access_token:' . $this->config->appId, 'wechat:access_token_lock:' . $this->config->appId);
        } finally {
            parent::tearDown();
        }
    }

    /** @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $responses */
    private function api(array $responses): MiniProgramApi
    {
        $http = $this->wechatClient($responses);

        return new MiniProgramApi($http, new AccessTokenProvider($http));
    }

    private function cacheToken(string $token): void
    {
        Redis::set('wechat:access_token:' . $this->config->appId, $token, 'EX', 600);
    }

    public function test_code2session_returns_openid_and_unionid_and_drops_session_key(): void
    {
        $api = $this->api([self::wechatJson(['openid' => 'o-mini', 'unionid' => 'u-mini', 'session_key' => 'SESSION-KEY-SECRET'])]);

        $result = $api->code2Session($this->config, self::CODE);

        $this->assertSame(['openid' => 'o-mini', 'unionid' => 'u-mini'], $result);
        $request = $this->wechatRequests()[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/sns/jscode2session', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame([
            'appid'      => $this->config->appId,
            'secret'     => self::SECRET,
            'js_code'    => self::CODE,
            'grant_type' => 'authorization_code',
        ], $query);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function withoutUnionid(): iterable
    {
        yield 'absent' => [['openid' => 'o-mini', 'session_key' => 'sk']];
        yield 'empty string' => [['openid' => 'o-mini', 'unionid' => '', 'session_key' => 'sk']];
        yield 'non string' => [['openid' => 'o-mini', 'unionid' => 123, 'session_key' => 'sk']];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('withoutUnionid')]
    public function test_code2session_normalises_missing_unionid_to_null(array $body): void
    {
        $this->assertSame(['openid' => 'o-mini', 'unionid' => null], $this->api([self::wechatJson($body)])->code2Session($this->config, self::CODE));
    }

    public function test_code2session_rejected_code_is_an_api_exception_without_leaking_the_code(): void
    {
        try {
            $this->api([self::wechatJson(['errcode' => 40029, 'errmsg' => 'invalid code'])])->code2Session($this->config, self::CODE);
            $this->fail('errcode 40029 必须抛 WechatApiException');
        } catch (WechatApiException $e) {
            $this->assertSame(40029, $e->getErrcode());
            $this->assertStringNotContainsString(self::CODE, $e->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
        }
    }

    public function test_code2session_without_openid_is_unavailable(): void
    {
        $this->expectException(WechatUnavailableException::class);

        $this->api([self::wechatJson(['session_key' => 'sk'])])->code2Session($this->config, self::CODE);
    }

    public function test_get_phone_number_posts_the_code_with_the_cached_access_token(): void
    {
        $this->cacheToken('AT-CACHED');
        $api = $this->api([self::wechatJson([
            'errcode'    => 0,
            'errmsg'     => 'ok',
            'phone_info' => ['phoneNumber' => '+86 13800138000', 'purePhoneNumber' => '13800138000', 'countryCode' => '86'],
        ])]);

        $this->assertSame('13800138000', $api->getPhoneNumber($this->config, self::PHONE_CODE));

        $requests = $this->wechatRequests();
        $this->assertCount(1, $requests, '缓存命中时只调一次手机号接口');
        $request = $requests[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/wxa/business/getuserphonenumber', $request->getUri()->getPath());
        $this->assertSame('access_token=AT-CACHED', $request->getUri()->getQuery());
        $this->assertSame('{"code":"' . self::PHONE_CODE . '"}', (string) $request->getBody());
    }

    /** @return iterable<string, array{int}> */
    public static function expiredTokenErrcodes(): iterable
    {
        yield '40001 invalid credential' => [40001];
        yield '40014 invalid access_token' => [40014];
        yield '42001 access_token expired' => [42001];
    }

    #[DataProvider('expiredTokenErrcodes')]
    public function test_get_phone_number_refreshes_the_token_and_retries_once(int $errcode): void
    {
        $this->cacheToken('AT-OLD');
        $api = $this->api([
            self::wechatJson(['errcode' => $errcode, 'errmsg' => 'token expired']),
            self::wechatJson(['access_token' => 'AT-NEW', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 0, 'phone_info' => ['purePhoneNumber' => '13900139000']]),
        ]);

        $this->assertSame('13900139000', $api->getPhoneNumber($this->config, self::PHONE_CODE));

        $requests = $this->wechatRequests();
        $this->assertCount(3, $requests);
        $this->assertSame('access_token=AT-OLD', $requests[0]['request']->getUri()->getQuery());
        $this->assertSame('/cgi-bin/token', $requests[1]['request']->getUri()->getPath());
        $this->assertSame('access_token=AT-NEW', $requests[2]['request']->getUri()->getQuery());
        $this->assertSame('AT-NEW', Redis::get('wechat:access_token:' . $this->config->appId));
    }

    public function test_get_phone_number_retries_only_once(): void
    {
        $this->cacheToken('AT-OLD');
        $api = $this->api([
            self::wechatJson(['errcode' => 40001]),
            self::wechatJson(['access_token' => 'AT-NEW', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 40001]),
        ]);

        try {
            $api->getPhoneNumber($this->config, self::PHONE_CODE);
            $this->fail('第二次仍失败必须抛出');
        } catch (WechatApiException $e) {
            $this->assertSame(40001, $e->getErrcode());
        }
        $this->assertCount(3, $this->wechatRequests(), '只重试一次，不得无限刷新 token');
    }

    public function test_get_phone_number_other_errcodes_are_not_retried(): void
    {
        $this->cacheToken('AT-OK');
        $api = $this->api([self::wechatJson(['errcode' => 40029, 'errmsg' => 'invalid code'])]);

        try {
            $api->getPhoneNumber($this->config, self::PHONE_CODE);
            $this->fail('errcode 40029 必须抛出');
        } catch (WechatApiException $e) {
            $this->assertSame(40029, $e->getErrcode());
            $this->assertStringNotContainsString(self::PHONE_CODE, $e->getMessage());
            $this->assertStringNotContainsString('AT-OK', $e->getMessage());
        }
        $this->assertCount(1, $this->wechatRequests());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function missingPhone(): iterable
    {
        yield 'no phone_info' => [['errcode' => 0]];
        yield 'phone_info not object' => [['errcode' => 0, 'phone_info' => 'x']];
        yield 'empty purePhoneNumber' => [['errcode' => 0, 'phone_info' => ['purePhoneNumber' => '']]];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('missingPhone')]
    public function test_get_phone_number_without_a_number_is_unavailable(array $body): void
    {
        $this->cacheToken('AT-OK');

        $this->expectException(WechatUnavailableException::class);

        $this->api([self::wechatJson($body)])->getPhoneNumber($this->config, self::PHONE_CODE);
    }
}
