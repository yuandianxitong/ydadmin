<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use tests\Support\ApiTestCase;

/** M6a spec §4.7 与设计决定 11：scope 白名单、回调地址必须在本站 host+port 下。 */
final class OauthUrlApiTest extends ApiTestCase
{
    private const OA_APP_ID = 'wx0a1b2c3d4e5f6a7b';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('wechat_official_app_id', self::OA_APP_ID);
        $this->setConfig('wechat_official_app_secret', 'oa-secret-for-tests');
        $this->setConfig('site_url', 'https://Shop.Example.com');
    }

    public function test_builds_authorize_url_with_default_snsapi_base(): void
    {
        $response = $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://shop.example.com/mobile/pages/index/index']);

        $response->assertOk();
        $url = (string) $response->data()['url'];
        $this->assertStringStartsWith('https://open.weixin.qq.com/connect/oauth2/authorize?', $url);
        $this->assertStringEndsWith('#wechat_redirect', $url);
        parse_str((string) parse_url(substr($url, 0, -strlen('#wechat_redirect')), PHP_URL_QUERY), $query);
        $this->assertSame(self::OA_APP_ID, $query['appid']);
        $this->assertSame('https://shop.example.com/mobile/pages/index/index', $query['redirect_uri']);
        $this->assertSame('snsapi_base', $query['scope']);
        $this->assertSame('code', $query['response_type']);
        $this->assertStringNotContainsString('oa-secret-for-tests', $url);
    }

    public function test_userinfo_scope_is_allowed_and_other_scopes_are_422(): void
    {
        $ok = $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://shop.example.com/m', 'scope' => 'snsapi_userinfo']);
        $ok->assertOk();
        $this->assertStringContainsString('scope=snsapi_userinfo', (string) $ok->data()['url']);

        $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://shop.example.com/m', 'scope' => 'snsapi_login'])->assertCode(422);
    }

    /** @return array<string, array{string}> */
    public static function foreignRedirects(): array
    {
        return [
            '外域'           => ['https://evil.example.net/steal'],
            '子域'           => ['https://evil.shop.example.com/'],
            '后缀拼接'       => ['https://shop.example.com.evil.net/'],
            '端口不同'       => ['https://shop.example.com:8443/m'],
            'http 对 https' => ['http://shop.example.com/m'],
            '非 http 协议'   => ['javascript://shop.example.com/%0Aalert(1)'],
            '相对地址'       => ['/mobile/pages/index/index'],
            '协议相对'       => ['//evil.example.net/'],
            '用户信息伪装'   => ['https://shop.example.com@evil.example.net/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('foreignRedirects')]
    public function test_redirect_outside_site_is_422(string $redirect): void
    {
        $response = $this->get('/api/wechat/oauth-url', ['redirect_url' => $redirect]);

        $response->assertCode(422);
        $this->assertSame(lang('wechat.redirect_not_allowed'), $response->data()['errors']['redirect_url'] ?? null);
    }

    public function test_explicit_default_port_matches_site_url_without_port(): void
    {
        $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://shop.example.com:443/m'])->assertOk();
    }

    public function test_empty_site_url_refuses_every_redirect(): void
    {
        $this->setConfig('site_url', '');

        $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://shop.example.com/m'])->assertCode(422);
    }

    public function test_not_configured(): void
    {
        $this->setConfig('wechat_official_app_id', '');

        $response = $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://shop.example.com/m']);

        $response->assertCode(400);
        $this->assertSame(lang('wechat.not_configured'), $response->message());
    }

    public function test_redirect_url_is_required(): void
    {
        $this->get('/api/wechat/oauth-url', [])->assertCode(422);
    }
}
