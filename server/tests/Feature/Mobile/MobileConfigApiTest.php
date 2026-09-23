<?php

declare(strict_types=1);

namespace tests\Feature\Mobile;

use support\Db;
use tests\Support\ApiTestCase;

final class MobileConfigApiTest extends ApiTestCase
{
    /** @var array<string, mixed>|null */
    private ?array $seed = null;

    protected function setUp(): void
    {
        parent::setUp();
        $row = Db::table('mobile_configs')->orderBy('id')->first();
        $this->seed = $row === null ? null : (array) $row;
    }

    protected function tearDown(): void
    {
        $this->restoreSeed();
        parent::tearDown();
    }

    public function test_admin_put_then_c_end_reads_theme_and_tabbar(): void
    {
        $admin = $this->actingAsAdmin('super');
        $before = $this->get('/adminapi/mobile/config', [], $admin->token)->assertCode(200)->data();
        $this->assertArrayNotHasKey('app_intro', $before);
        $this->assertArrayHasKey('theme_color', $before);
        $this->assertArrayHasKey('tabbar', $before);

        $eligible = $this->get('/adminapi/mobile/config/eligible', [], $admin->token)->assertCode(200)->data();
        $this->assertSame([], $eligible['homeOptions']);
        $this->assertCount(4, $eligible['tabBarOptions']);
        $this->assertSame('builtin', $eligible['tabBarOptions'][0]['kind']);
        $this->assertSame('__home__', $eligible['tabBarOptions'][0]['code']);

        $this->put('/adminapi/mobile/config', [
            'theme_colors' => ['primary' => '#ff0000'],
            'tabbar' => [
                ['code' => '__home__', 'path' => 'pages/index/index', 'text' => '首页'],
            ],
            'app_intro' => '应被忽略',
        ], $admin->token)->assertCode(200);

        $c = $this->get('/api/mobile/config')->assertCode(200)->data();
        $this->assertSame('#ff0000', $c['theme_color']);
        $this->assertSame('首页', $c['tabbar'][0]['text']);
        $this->assertArrayHasKey('home_decoration', $c);

        $this->put('/adminapi/mobile/config', [
            'tabbar' => [
                ['code' => 'a', 'path' => 'p', 'text' => '1'],
                ['code' => 'b', 'path' => 'p', 'text' => '2'],
                ['code' => 'c', 'path' => 'p', 'text' => '3'],
                ['code' => 'd', 'path' => 'p', 'text' => '4'],
                ['code' => 'e', 'path' => 'p', 'text' => '5'],
                ['code' => 'f', 'path' => 'p', 'text' => '6'],
            ],
        ], $admin->token)->assertCode(422);

        // 恢复种子主题，避免污染
        $this->put('/adminapi/mobile/config', [
            'theme_color' => '#2979ff',
            'theme_colors' => [
                'primary' => '#2979ff', 'dark' => '#1e5bb8', 'price' => '#fa3534',
                'page_bg' => '#f5f5f5', 'button_text' => '#ffffff', 'badge' => '#fa3534',
            ],
            'tabbar' => [
                ['code' => '__home__', 'path' => 'pages/index/index', 'text' => '首页',
                 'icon' => '/static/diy/tabbar/home.png', 'selected_icon' => '/static/diy/tabbar/home-active.png'],
                ['code' => '__discover__', 'path' => 'pages/discover/index', 'text' => '发现',
                 'icon' => '/static/diy/tabbar/discover.png', 'selected_icon' => '/static/diy/tabbar/discover-active.png'],
                ['code' => '__message__', 'path' => 'pages/message/index', 'text' => '消息',
                 'icon' => '/static/diy/tabbar/message.png', 'selected_icon' => '/static/diy/tabbar/message-active.png'],
                ['code' => '__my__', 'path' => 'pages/my/index', 'text' => '我的',
                 'icon' => '/static/diy/tabbar/my.png', 'selected_icon' => '/static/diy/tabbar/my-active.png'],
            ],
        ], $admin->token)->assertCode(200);
    }

    private function restoreSeed(): void
    {
        if ($this->seed === null) {
            return;
        }
        $id = (int) $this->seed['id'];
        $data = $this->seed;
        unset($data['id']);
        Db::table('mobile_configs')->where('id', $id)->update($data);
    }

    /** 传数组给字符串字段时 PHP 的 Array to string conversion 会被 webman 转成 ErrorException → 500。 */
    public function test_array_values_are_rejected_with_422(): void
    {
        $admin = $this->actingAsAdmin('super');

        foreach (['app_name', 'app_logo', 'theme_color', 'home_app_code', 'home_page'] as $field) {
            $response = $this->put('/adminapi/mobile/config', [$field => ['a' => 1]], $admin->token);
            $this->assertSame(200, $response->status(), "{$field} 不能是未捕获异常");
            $this->assertSame(422, $response->code(), "{$field} 应按校验失败处理");
        }
    }
}
