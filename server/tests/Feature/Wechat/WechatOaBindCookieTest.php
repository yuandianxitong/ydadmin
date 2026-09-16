<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\service\wechat\WechatOaBindCookie;
use core\wechat\exception\WechatNotConfiguredException;
use support\Container;
use support\Response;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

/** M6a spec §5：公众号绑定证明 cookie 的签发、校验与下发属性。 */
final class WechatOaBindCookieTest extends ApiTestCase
{
    use ConfigOverride;

    private const OPENID = 'oOA_bind_test_openid_0001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideConfig('auth.jwt.user.key', 'unit-test-user-secret-0123456789abcdef');
        $this->overrideConfig('wechat.oa_bind_cookie_ttl', 604800);
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreConfig();
        } finally {
            parent::tearDown();
        }
    }

    private function cookie(): WechatOaBindCookie
    {
        return Container::get(WechatOaBindCookie::class);
    }

    public function test_issue_then_verify_returns_openid(): void
    {
        $now = 1_800_000_000;
        $value = $this->cookie()->issue(self::OPENID, $now);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.\d+\.[A-Za-z0-9_-]+$/', $value, '值只含 URL 安全字符，rawurlencode 下发后原样回传');
        $this->assertSame(self::OPENID, $this->cookie()->verify($value, $now + 60));
    }

    public function test_verify_rejects_tampered_openid_timestamp_or_mac(): void
    {
        $now = 1_800_000_000;
        [$openid, $issuedAt, $mac] = explode('.', $this->cookie()->issue(self::OPENID, $now));
        $otherOpenid = rtrim(strtr(base64_encode('oOA_attacker_openid_9999'), '+/', '-_'), '=');

        $this->assertNull($this->cookie()->verify("{$otherOpenid}.{$issuedAt}.{$mac}", $now), '换 openid');
        $this->assertNull($this->cookie()->verify("{$openid}." . ($now + 1000) . ".{$mac}", $now + 1000), '改签发时间');
        $this->assertNull($this->cookie()->verify("{$openid}.{$issuedAt}." . strrev($mac), $now), '改签名');
        $this->assertNull($this->cookie()->verify("{$openid}.{$issuedAt}", $now), '缺段');
        $this->assertNull($this->cookie()->verify("{$openid}.12a4.{$mac}", $now), '时间非数字');
        $this->assertNull($this->cookie()->verify('', $now));
        $this->assertNull($this->cookie()->verify(null, $now));
        $this->assertNull($this->cookie()->verify(str_repeat('a', 600), $now), '超长值直接拒绝');
    }

    public function test_verify_rejects_expired_and_far_future(): void
    {
        $now = 1_800_000_000;
        $value = $this->cookie()->issue(self::OPENID, $now);

        $this->assertSame(self::OPENID, $this->cookie()->verify($value, $now + 604800), '恰好到期仍有效');
        $this->assertNull($this->cookie()->verify($value, $now + 604801), '过期');
        $this->assertNull($this->cookie()->verify($value, $now - 61), '签发时间在未来超过 60 秒');
    }

    public function test_cookie_signed_with_another_secret_is_rejected(): void
    {
        $value = $this->cookie()->issue(self::OPENID, 1_800_000_000);
        $this->overrideConfig('auth.jwt.user.key', 'another-secret-another-secret-00');

        $this->assertNull($this->cookie()->verify($value, 1_800_000_000));
    }

    public function test_empty_secret_refuses_to_issue_and_verify(): void
    {
        $value = $this->cookie()->issue(self::OPENID, 1_800_000_000);
        $this->overrideConfig('auth.jwt.user.key', '');

        $this->assertNull($this->cookie()->verify($value, 1_800_000_000), '空密钥下任何值都不是有效证明');
        $this->expectException(WechatNotConfiguredException::class);
        $this->cookie()->issue(self::OPENID, 1_800_000_000);
    }

    public function test_attach_sets_httponly_lax_api_path_and_max_age_without_secure_on_http(): void
    {
        $this->setConfig('site_url', 'http://shop.example.com');

        $header = (string) $this->cookie()->attach(new Response(200), self::OPENID)->getHeaders()['Set-Cookie'][0];

        $this->assertStringStartsWith(WechatOaBindCookie::NAME . '=', $header);
        $this->assertStringContainsString('; Max-Age=604800', $header);
        $this->assertStringContainsString('; Path=/api', $header);
        $this->assertStringContainsString('; HttpOnly', $header);
        $this->assertStringContainsString('; SameSite=Lax', $header);
        $this->assertStringNotContainsString('Secure', $header);
        $value = substr(explode(';', $header)[0], strlen(WechatOaBindCookie::NAME) + 1);
        $this->assertSame(self::OPENID, $this->cookie()->verify($value));
    }

    public function test_attach_adds_secure_when_site_url_is_https(): void
    {
        $this->setConfig('site_url', 'HTTPS://shop.example.com/');

        $header = (string) $this->cookie()->attach(new Response(200), self::OPENID)->getHeaders()['Set-Cookie'][0];

        $this->assertStringContainsString('; Secure', $header);
    }

    public function test_forget_expires_the_cookie(): void
    {
        $this->setConfig('site_url', 'http://shop.example.com');

        $header = (string) $this->cookie()->forget(new Response(200))->getHeaders()['Set-Cookie'][0];

        $this->assertStringStartsWith(WechatOaBindCookie::NAME . '=;', $header);
        $this->assertStringContainsString('; Max-Age=0', $header);
        $this->assertStringContainsString('; Path=/api', $header);
    }
}
