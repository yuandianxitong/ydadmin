<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;

/**
 * 红线（M6a spec §4.1 第 2 步）：按 unionid 命中的用户，只有本端 openid 列为空时才补写；已有**别的值**时视为未命中、
 * 绝不覆盖。1.x 在这里静默覆盖——同一 unionid 下出现第二个小程序/公众号/网页 openid（例如开放平台下挂了另一个应用、
 * 或 unionid 被错误复用）时，老用户的绑定被抢走，新 openid 直接登进老账号。
 *
 * 三个端各测一次；另有正向对照：本端列为空时按 unionid 补绑并登进老账号，证明「不覆盖」不是「从不按 unionid 匹配」。
 */
final class Test30_WechatLoginNeverOverwritesBindingTest extends ApiTestCase
{
    use FakeWechatHttp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('site_url', 'https://m30.example.com');
        foreach (['mini', 'official', 'open'] as $side) {
            $this->setConfig("wechat_{$side}_app_id", 'wx30' . bin2hex(random_bytes(6)));
            $this->setConfig("wechat_{$side}_app_secret", 'secret30' . bin2hex(random_bytes(8)));
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

    public function test_mini_login_does_not_take_over_existing_mini_openid(): void
    {
        $unionid = 'UN30M' . bin2hex(random_bytes(8));
        $owner = $this->actingAsUser(['unionid' => $unionid, 'mini_openid' => 'MINI30OLD' . bin2hex(random_bytes(6))]);
        $ownerBefore = (array) Db::table('users')->where('id', $owner->id)->first();
        $newOpenid = 'MINI30NEW' . bin2hex(random_bytes(6));
        $this->fakeWechatHttp([self::wechatJson(['openid' => $newOpenid, 'unionid' => $unionid, 'session_key' => 'SK30'])]);

        $response = $this->post('/api/auth/wechat-login', ['code' => 'code30m'])->assertOk();

        $loggedInId = (int) (((array) (((array) $response->data())['user_info'] ?? []))['id'] ?? 0);
        $this->trackUser($loggedInId);
        $this->assertNotSame($owner->id, $loggedInId, '新 openid 不能登进 unionid 相同但已绑定别的 openid 的老账号');
        $this->assertSame($ownerBefore, (array) Db::table('users')->where('id', $owner->id)->first(), '老账号整行不能被改');
        $this->assertSame($newOpenid, Db::table('users')->where('id', $loggedInId)->value('mini_openid'));
    }

    public function test_web_login_does_not_take_over_existing_openid(): void
    {
        $unionid = 'UN30W' . bin2hex(random_bytes(8));
        $owner = $this->actingAsUser(['unionid' => $unionid, 'openid' => 'WEB30OLD' . bin2hex(random_bytes(6))]);
        $ownerBefore = (array) Db::table('users')->where('id', $owner->id)->first();
        $newOpenid = 'WEB30NEW' . bin2hex(random_bytes(6));
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'AT30W', 'expires_in' => 7200, 'refresh_token' => 'RT30W', 'openid' => $newOpenid, 'scope' => 'snsapi_login', 'unionid' => $unionid]),
            self::wechatJson(['openid' => $newOpenid, 'nickname' => '网页新号', 'headimgurl' => '', 'unionid' => $unionid]),
        ]);

        $response = $this->post('/api/auth/wechat-web-login', ['code' => 'code30w'])->assertOk();

        $loggedInId = (int) (((array) (((array) $response->data())['user_info'] ?? []))['id'] ?? 0);
        $this->trackUser($loggedInId);
        $this->assertNotSame($owner->id, $loggedInId);
        $this->assertSame($ownerBefore, (array) Db::table('users')->where('id', $owner->id)->first());
    }

    public function test_h5_login_does_not_log_into_account_with_other_oa_openid(): void
    {
        $unionid = 'UN30H' . bin2hex(random_bytes(8));
        $owner = $this->actingAsUser(['unionid' => $unionid, 'oa_openid' => 'OA30OLD' . bin2hex(random_bytes(6))]);
        $ownerBefore = (array) Db::table('users')->where('id', $owner->id)->first();
        $newOpenid = 'OA30NEW' . bin2hex(random_bytes(6));
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'AT30H', 'expires_in' => 7200, 'openid' => $newOpenid, 'scope' => 'snsapi_base', 'unionid' => $unionid]),
        ]);

        $response = $this->post('/api/auth/wechat-h5-login', ['code' => 'code30h'])->assertOk();

        $data = (array) $response->data();
        $this->assertSame('need_login', $data['status'] ?? null, '不能以老账号身份登录');
        $this->assertArrayNotHasKey('token', $data);
        $this->assertSame($ownerBefore, (array) Db::table('users')->where('id', $owner->id)->first());
        $this->assertSame(0, Db::table('users')->where('oa_openid', $newOpenid)->count(), 'h5-login 不注册、不绑定');
    }

    public function test_empty_column_is_filled_by_unionid_match(): void
    {
        $unionid = 'UN30P' . bin2hex(random_bytes(8));
        $owner = $this->actingAsUser(['unionid' => $unionid]);
        $openid = 'MINI30P' . bin2hex(random_bytes(6));
        $this->fakeWechatHttp([self::wechatJson(['openid' => $openid, 'unionid' => $unionid, 'session_key' => 'SK30P'])]);

        $response = $this->post('/api/auth/wechat-login', ['code' => 'code30p'])->assertOk();

        // 正向对照：否则「unionid 永不匹配」也能让上面三条通过
        $this->assertSame($owner->id, (int) (((array) (((array) $response->data())['user_info'] ?? []))['id'] ?? 0));
        $this->assertSame($openid, Db::table('users')->where('id', $owner->id)->value('mini_openid'));
    }
}
