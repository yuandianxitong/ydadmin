<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use core\contract\ConfigValueReader;
use core\wechat\AccessTokenProvider;
use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatNotConfiguredException;
use core\wechat\OfficialAccountApi;
use core\wechat\WechatConfigResolver;
use support\Redis;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

final class OfficialAccountApiTest extends TestCase
{
    use FakeWechatHttp;

    private string $appId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appId = 'wxofficial' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        try {
            Redis::del('wechat:access_token:' . $this->appId, 'wechat:access_token_lock:' . $this->appId);
        } finally {
            parent::tearDown();
        }
    }

    /** @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $responses */
    private function api(array $responses, bool $configured = true): OfficialAccountApi
    {
        $http = $this->wechatClient($responses);
        $values = $configured ? [
            'wechat_official_app_id'     => $this->appId,
            'wechat_official_app_secret' => 'SECRET-official-app',
        ] : [];
        $configs = new WechatConfigResolver(new class ($values) implements ConfigValueReader {
            /** @param array<string, mixed> $values */
            public function __construct(private readonly array $values)
            {
            }

            public function getConfigValue(string $key, mixed $default = null): mixed
            {
                return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
            }
        });

        return new OfficialAccountApi($http, new AccessTokenProvider($http), $configs);
    }

    private function cacheToken(string $token = 'AT-OFFICIAL-CACHED'): void
    {
        Redis::set('wechat:access_token:' . $this->appId, $token, 'EX', 600);
    }

    public function test_get_current_self_menu_uses_cached_access_token(): void
    {
        $this->cacheToken();
        $api = $this->api([self::wechatJson(['is_menu_open' => 1])]);

        $this->assertSame(['is_menu_open' => 1], $api->getCurrentSelfMenu());

        $requests = $this->wechatRequests();
        $this->assertCount(1, $requests);
        $request = $requests[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/cgi-bin/get_current_selfmenu_info', $request->getUri()->getPath());
        $this->assertSame('access_token=AT-OFFICIAL-CACHED', $request->getUri()->getQuery());
    }

    public function test_create_menu_posts_buttons_with_cached_access_token(): void
    {
        $this->cacheToken();
        $button = [['name' => 'a', 'type' => 'view', 'url' => 'https://x.test']];
        $api = $this->api([self::wechatJson(['errcode' => 0, 'errmsg' => 'ok'])]);

        $this->assertSame(['errcode' => 0, 'errmsg' => 'ok'], $api->createMenu($button));

        $requests = $this->wechatRequests();
        $this->assertCount(1, $requests);
        $request = $requests[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/cgi-bin/menu/create', $request->getUri()->getPath());
        $this->assertSame('access_token=AT-OFFICIAL-CACHED', $request->getUri()->getQuery());
        $this->assertSame(['button' => $button], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_delete_menu_uses_cached_access_token(): void
    {
        $this->cacheToken();
        $api = $this->api([self::wechatJson(['errcode' => 0, 'errmsg' => 'ok'])]);

        $this->assertSame(['errcode' => 0, 'errmsg' => 'ok'], $api->deleteMenu());

        $requests = $this->wechatRequests();
        $this->assertCount(1, $requests);
        $request = $requests[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/cgi-bin/menu/delete', $request->getUri()->getPath());
        $this->assertSame('access_token=AT-OFFICIAL-CACHED', $request->getUri()->getQuery());
    }

    public function test_wechat_error_is_preserved_as_api_exception(): void
    {
        $this->cacheToken();
        $api = $this->api([self::wechatJson(['errcode' => 40018, 'errmsg' => 'invalid button'])]);

        try {
            $api->createMenu([['name' => 'a', 'type' => 'view', 'url' => 'https://x.test']]);
            $this->fail('微信菜单错误必须抛 WechatApiException');
        } catch (WechatApiException $e) {
            $this->assertSame(40018, $e->getErrcode());
            $this->assertSame('invalid button', $e->getErrmsg());
        }
    }

    public function test_unconfigured_official_account_fails_without_http_request(): void
    {
        $api = $this->api([], false);

        try {
            $api->getCurrentSelfMenu();
            $this->fail('公众号未配置必须抛 WechatNotConfiguredException');
        } catch (WechatNotConfiguredException) {
            $this->assertSame([], $this->wechatRequests());
        }
    }
}
