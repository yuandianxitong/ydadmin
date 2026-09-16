<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\service\wechat\WechatOaBindCookie;
use PHPUnit\Framework\Attributes\DataProvider;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;

/**
 * 红线（M6a spec §4.8、§5）：`POST /api/user/bind-oa-openid` 只认 `wechat-h5-login` 下发的签名 HttpOnly cookie。
 * 1.x 直接把客户端传来的 oa_openid 写进用户行——谁知道别人的 openid，谁就能把它绑到自己账号上，
 * 截走对方的公众号消息、在对方身份下走 JSAPI 支付。
 *
 * 失败用例全部经真实路由：没有 cookie、伪造 HMAC、拿自己 openid 的真 cookie 去绑别人的 openid、
 * 把真 cookie 里的 openid 段换成受害者的、过期 cookie——都必须被拒绝，且数据库里谁也没多出一个 oa_openid。
 * 正向对照走完整链：h5-login（假微信换出 openid）→ 响应下发 cookie → 原样带回 → 绑定成功。
 */
final class Test27_OaOpenidBindForgeryTest extends ApiTestCase
{
    use FakeWechatHttp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('site_url', 'https://m27.example.com');
        $this->setConfig('wechat_official_app_id', 'wx27' . bin2hex(random_bytes(6)));
        $this->setConfig('wechat_official_app_secret', 'secret27' . bin2hex(random_bytes(8)));
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreWechatHttp();
        } finally {
            parent::tearDown();
        }
    }

    /** @return array<string, array{string}> */
    public static function forgeries(): array
    {
        return [
            '没有 cookie'                      => ['missing'],
            'HMAC 是随手编的'                  => ['forged_hmac'],
            '自己 openid 的真 cookie 绑别人 openid' => ['own_cookie_other_openid'],
            '真 cookie 里的 openid 段换成受害者'   => ['swapped_openid_segment'],
            '过期 cookie（签发于 7 天零 1 秒前）'  => ['expired'],
        ];
    }

    #[DataProvider('forgeries')]
    public function test_bind_without_valid_proof_is_rejected(string $case): void
    {
        $attacker = $this->actingAsUser();
        $victimOpenid = 'OA27V' . bin2hex(random_bytes(8));
        $attackerOpenid = 'OA27A' . bin2hex(random_bytes(8));
        $cookie = $this->cookieService();

        $cookieValue = match ($case) {
            'missing'                 => null,
            'forged_hmac'             => self::b64($victimOpenid) . '.' . time() . '.' . self::b64(random_bytes(32)),
            'own_cookie_other_openid' => $cookie->issue($attackerOpenid),
            'swapped_openid_segment'  => self::swapOpenidSegment($cookie->issue($attackerOpenid), $victimOpenid),
            'expired'                 => $cookie->issue($victimOpenid, time() - 604801),
        };
        $headers = $cookieValue === null ? [] : ['Cookie' => WechatOaBindCookie::NAME . '=' . $cookieValue];

        $response = $this->post('/api/user/bind-oa-openid', ['oa_openid' => $victimOpenid], $attacker->token, $headers);

        $this->assertSame(200, $response->status(), "{$case}：业务错误走 HTTP 200");
        $response->assertCode(400);
        $this->assertSame(lang('wechat.oa_bind_invalid'), $response->message(), "{$case}：拒绝文案");
        $this->assertNull(Db::table('users')->where('id', $attacker->id)->value('oa_openid'), "{$case}：攻击者账号不能绑上任何 openid");
        $this->assertSame(0, Db::table('users')->where('oa_openid', $victimOpenid)->count(), "{$case}：受害者的 openid 不能出现在任何用户行上");
    }

    public function test_openid_already_bound_to_another_account_is_not_stolen_even_with_a_valid_cookie(): void
    {
        $victimOpenid = 'OA27B' . bin2hex(random_bytes(8));
        $victim = $this->actingAsUser(['oa_openid' => $victimOpenid]);
        $attacker = $this->actingAsUser();
        // 最坏情形：攻击者手里居然有一枚针对该 openid 的有效 cookie——仍不能把已属于别人的 openid 抢过来
        $cookieValue = $this->cookieService()->issue($victimOpenid);

        $response = $this->post('/api/user/bind-oa-openid', ['oa_openid' => $victimOpenid], $attacker->token, [
            'Cookie' => WechatOaBindCookie::NAME . '=' . $cookieValue,
        ]);

        $response->assertCode(400);
        $this->assertSame(lang('wechat.wechat_bound_other_account'), $response->message());
        $this->assertSame($victimOpenid, Db::table('users')->where('id', $victim->id)->value('oa_openid'), '受害者的绑定不能被动');
        $this->assertNull(Db::table('users')->where('id', $attacker->id)->value('oa_openid'));
    }

    public function test_cookie_issued_by_h5_login_binds_successfully(): void
    {
        $openid = 'OA27P' . bin2hex(random_bytes(8));
        $user = $this->actingAsUser();
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'AT27' . bin2hex(random_bytes(8)), 'expires_in' => 7200, 'openid' => $openid, 'scope' => 'snsapi_base']),
        ]);

        $login = $this->post('/api/auth/wechat-h5-login', ['code' => 'code27' . bin2hex(random_bytes(4))])->assertOk();
        $this->assertSame('need_login', ((array) $login->data())['status'] ?? null, '前置条件：该 openid 还没绑定任何人');
        $setCookie = (string) $login->header('Set-Cookie');
        $this->assertMatchesRegularExpression('/(^|, )' . WechatOaBindCookie::NAME . '=[A-Za-z0-9_.-]+;/', $setCookie, '正向对照：h5-login 必须下发绑定 cookie');
        $this->assertStringContainsString('HttpOnly', $setCookie);
        $this->assertStringContainsString('SameSite=Lax', $setCookie);
        $this->assertStringContainsString('Path=/api', $setCookie);
        $this->assertStringContainsString('Secure', $setCookie, 'site_url 为 https 时 cookie 必须带 Secure');
        preg_match('/' . WechatOaBindCookie::NAME . '=([A-Za-z0-9_.-]+);/', $setCookie, $m);

        $bind = $this->post('/api/user/bind-oa-openid', ['oa_openid' => $openid], $user->token, [
            'Cookie' => WechatOaBindCookie::NAME . '=' . $m[1],
        ]);

        $bind->assertOk();
        $this->assertSame($openid, Db::table('users')->where('id', $user->id)->value('oa_openid'), '正向对照：凭真 cookie 必须绑定成功，否则上面的失败用例证明不了什么');
        $this->assertStringContainsString('Max-Age=0', (string) $bind->header('Set-Cookie'), '绑定成功后清除 cookie');
    }

    private function cookieService(): WechatOaBindCookie
    {
        return Container::get(WechatOaBindCookie::class);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function swapOpenidSegment(string $cookieValue, string $openid): string
    {
        $parts = explode('.', $cookieValue);
        $parts[0] = self::b64($openid);

        return implode('.', $parts);
    }
}
