<?php

declare(strict_types=1);

namespace tests\RedLine;

use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\ApiTestCase;

/**
 * 红线（M6a spec §4.7、设计决定 11）：`GET /api/wechat/oauth-url` 只为本站域名生成授权链接。1.x 不校验
 * redirect_url，配合 oauth-callback 就是开放跳转——攻击者用本站的公众号 appid 把用户引到自己的站，
 * 顺手拿走 code。scheme、host（含 userinfo 伪装、子域后缀伪装）、端口任一不符都拒绝，响应里不出现 url。
 */
final class Test28_OauthUrlForeignRedirectTest extends ApiTestCase
{
    private string $appId = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->appId = 'wx28' . bin2hex(random_bytes(6));
        $this->setConfig('site_url', 'https://m28.example.com');
        $this->setConfig('wechat_official_app_id', $this->appId);
        $this->setConfig('wechat_official_app_secret', 'secret28' . bin2hex(random_bytes(8)));
    }

    /** @return array<string, array{string}> */
    public static function foreignRedirects(): array
    {
        return [
            '外域'                    => ['https://evil.example.org/cb'],
            '本站域名作前缀的外域'      => ['https://m28.example.com.evil.example.org/cb'],
            'userinfo 伪装'           => ['https://m28.example.com@evil.example.org/cb'],
            '同域不同端口'             => ['https://m28.example.com:8443/cb'],
            '同域 http（端口 80≠443）' => ['http://m28.example.com/cb'],
            '协议相对地址'             => ['//m28.example.com/cb'],
            'javascript 伪协议'        => ['javascript:alert(1)//m28.example.com'],
            '相对路径'                 => ['/mobile/pages/index'],
        ];
    }

    #[DataProvider('foreignRedirects')]
    public function test_foreign_redirect_is_rejected(string $redirect): void
    {
        $response = $this->get('/api/wechat/oauth-url', ['redirect_url' => $redirect, 'scope' => 'snsapi_base']);

        $response->assertCode(422);
        $this->assertArrayHasKey('redirect_url', (array) (((array) $response->data())['errors'] ?? []), "{$redirect}：错误要落在 redirect_url 上");
        $this->assertStringNotContainsString('open.weixin.qq.com', $response->body(), "{$redirect}：不能生成授权链接");
        $this->assertStringNotContainsString($this->appId, $response->body(), "{$redirect}：不能回显 appid");
    }

    public function test_empty_site_url_rejects_everything(): void
    {
        $this->setConfig('site_url', '');

        $response = $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://m28.example.com/cb', 'scope' => 'snsapi_base']);

        $response->assertCode(422);
        $this->assertStringNotContainsString('open.weixin.qq.com', $response->body());
    }

    public function test_scope_outside_whitelist_is_rejected(): void
    {
        $response = $this->get('/api/wechat/oauth-url', ['redirect_url' => 'https://m28.example.com/cb', 'scope' => 'snsapi_login']);

        $response->assertCode(422);
        $this->assertArrayHasKey('scope', (array) (((array) $response->data())['errors'] ?? []));
    }

    /** @return array<string, array{string}> */
    public static function ownRedirects(): array
    {
        return [
            '本站 https 缺省端口'   => ['https://m28.example.com/mobile/pages/index?from=menu'],
            '本站显式 443、大写主机' => ['https://M28.EXAMPLE.COM:443/mobile/'],
        ];
    }

    #[DataProvider('ownRedirects')]
    public function test_own_domain_is_allowed(string $redirect): void
    {
        $response = $this->get('/api/wechat/oauth-url', ['redirect_url' => $redirect])->assertOk();

        // 正向对照：否则「一律 422」也能让上面的用例通过
        $url = (string) (((array) $response->data())['url'] ?? '');
        $this->assertStringStartsWith('https://open.weixin.qq.com/connect/oauth2/authorize?', $url);
        $this->assertStringContainsString('appid=' . $this->appId, $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode($redirect), $url);
        $this->assertStringContainsString('scope=snsapi_base', $url, 'scope 缺省为 snsapi_base');
        $this->assertStringEndsWith('#wechat_redirect', $url);
    }
}
