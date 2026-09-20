<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;

final class OfficialAccountMenuApiTest extends ApiTestCase
{
    use FakeWechatHttp;

    private const BASE = '/adminapi/wechat/official/menu';
    private const APP_ID = 'wxofficialmenutest';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('wechat_official_app_id', self::APP_ID);
        $this->setConfig('wechat_official_app_secret', 'official-menu-secret');
        foreach ([
            'channel.official.menu',
            'channel.official.menu.create',
            'channel.official.menu.delete',
        ] as $permission) {
            if (Db::table('menus')->where('permission', $permission)->exists()) {
                continue;
            }
            $id = (int) Db::table('menus')->insertGetId([
                'parent_id' => 0,
                'type' => 3,
                'title' => '公众号菜单测试权限',
                'permission' => $permission,
            ]);
            $this->track('menus', $id);
        }
    }

    protected function tearDown(): void
    {
        try {
            Redis::del('wechat:access_token:' . self::APP_ID, 'wechat:access_token_lock:' . self::APP_ID);
            $this->restoreWechatHttp();
        } finally {
            parent::tearDown();
        }
    }

    private function cacheToken(): void
    {
        Redis::set('wechat:access_token:' . self::APP_ID, 'AT-MENU-API', 'EX', 600);
    }

    public function test_every_endpoint_requires_its_permission(): void
    {
        $nobody = $this->actingAsAdmin();

        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->post(self::BASE, ['button' => [['name' => '首页', 'type' => 'view', 'url' => 'https://x.test']]], $nobody->token)->assertCode(403);
        $this->delete(self::BASE, [], $nobody->token)->assertCode(403);
    }

    public function test_get_returns_official_account_payload_unchanged(): void
    {
        $this->cacheToken();
        $this->fakeWechatHttp([self::wechatJson([
            'is_menu_open' => 1,
            'selfmenu_info' => ['button' => [['name' => '首页', 'type' => 'view', 'url' => 'https://x.test']]],
        ])]);
        $admin = $this->actingAsAdmin(['channel.official.menu']);

        $response = $this->get(self::BASE, [], $admin->token)->assertOk();

        $this->assertSame('首页', $response->data()['selfmenu_info']['button'][0]['name']);
    }

    public function test_get_reports_official_account_not_configured(): void
    {
        $this->setConfig('wechat_official_app_id', '');
        $this->fakeWechatHttp([]);
        $admin = $this->actingAsAdmin(['channel.official.menu']);

        $response = $this->get(self::BASE, [], $admin->token)->assertCode(400);

        $this->assertSame(lang('wechat.official_not_configured'), $response->message());
    }

    public function test_create_menu_accepts_valid_payload_and_rejects_empty_button(): void
    {
        $this->cacheToken();
        $this->fakeWechatHttp([self::wechatJson(['errcode' => 0, 'errmsg' => 'ok'])]);
        $admin = $this->actingAsAdmin(['channel.official.menu.create']);

        $this->post(self::BASE, [
            'button' => [['name' => '首页', 'type' => 'view', 'url' => 'https://x.test']],
        ], $admin->token)->assertOk();

        $invalid = $this->post(self::BASE, ['button' => []], $admin->token)->assertCode(422);
        $this->assertArrayHasKey('button', $invalid->data()['errors']);
    }

    public function test_create_menu_surfaces_safe_wechat_error(): void
    {
        $this->cacheToken();
        $this->fakeWechatHttp([self::wechatJson(['errcode' => 40018, 'errmsg' => 'invalid button size'])]);
        $admin = $this->actingAsAdmin(['channel.official.menu.create']);

        $response = $this->post(self::BASE, [
            'button' => [['name' => '首页', 'type' => 'view', 'url' => 'https://x.test']],
        ], $admin->token)->assertCode(400);

        $this->assertStringContainsString('invalid button size', $response->message());
        $this->assertStringNotContainsString('access_token', $response->message());
    }

    public function test_delete_menu_succeeds(): void
    {
        $this->cacheToken();
        $this->fakeWechatHttp([self::wechatJson(['errcode' => 0, 'errmsg' => 'ok'])]);
        $admin = $this->actingAsAdmin(['channel.official.menu.delete']);

        $this->delete(self::BASE, [], $admin->token)->assertOk();
    }
}
