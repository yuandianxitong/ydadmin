<?php

declare(strict_types=1);

namespace tests\Feature\Diy;

use tests\Support\ApiTestCase;

final class DiyLinkApiTest extends ApiTestCase
{
    public function test_link_crud_and_catalog_contains_library_and_builtin(): void
    {
        $admin = $this->actingAsAdmin('super');
        $created = $this->post('/adminapi/diy/links', [
            'label'    => '外链',
            'path'     => 'https://example.com/x',
            'category' => '我的链接',
            'sort'     => 1,
            'status'   => 1,
        ], $admin->token)->assertCode(200)->data();
        $this->track('diy_links', (int) $created['id']);

        $list = $this->get('/adminapi/diy/links', [], $admin->token)->assertCode(200)->data();
        $this->assertIsArray($list);
        $this->assertArrayNotHasKey('pagination', $list);

        $catalog = $this->get('/adminapi/diy/link-catalog', [], $admin->token)->assertCode(200)->data();
        $this->assertArrayHasKey('links', $catalog);
        $paths = array_column($catalog['links'], 'path');
        $this->assertContains('/pages/index/index', $paths);
        $this->assertContains('https://example.com/x', $paths);
        $library = array_values(array_filter($catalog['links'], fn ($l) => ($l['source'] ?? '') === 'library'));
        $this->assertSame(true, $library[0]['external']);

        $expectedBuiltin = [
            ['label' => '首页',     'path' => '/pages/index/index',                      'category' => '基础页面'],
            ['label' => '发现',     'path' => '/pages/discover/index',                   'category' => '基础页面'],
            ['label' => '消息',     'path' => '/pages/message/index',                    'category' => '基础页面'],
            ['label' => '我的',     'path' => '/pages/my/index',                         'category' => '基础页面'],
            ['label' => '登录',     'path' => '/modules/login/pages/login',              'category' => '用户中心'],
            ['label' => '注册',     'path' => '/modules/login/pages/register',           'category' => '用户中心'],
            ['label' => '个人资料', 'path' => '/modules/user/pages/edit-profile',        'category' => '用户中心'],
            ['label' => '修改密码', 'path' => '/modules/user/pages/change-password',     'category' => '用户中心'],
            ['label' => '余额',     'path' => '/modules/user/pages/balance',             'category' => '用户中心'],
            ['label' => '积分',     'path' => '/modules/user/pages/points',              'category' => '用户中心'],
            ['label' => '设置',     'path' => '/modules/user/pages/settings',            'category' => '用户中心'],
            ['label' => '意见反馈', 'path' => '/modules/feedback/pages/feedback',        'category' => '用户中心'],
            ['label' => '关于我们', 'path' => '/modules/about/pages/about',              'category' => '用户中心'],
            ['label' => '公告列表', 'path' => '/modules/announcement/pages/announcement-list', 'category' => '内容'],
            ['label' => '文章列表', 'path' => '/modules/article/pages/article-list',     'category' => '内容'],
            ['label' => '用户协议', 'path' => '/modules/agreement/pages/agreement?code=user_agreement', 'category' => '内容'],
        ];
        $builtins = array_values(array_filter($catalog['links'], fn ($l) => ($l['source'] ?? '') === 'builtin'));
        $this->assertCount(16, $builtins);
        foreach ($expectedBuiltin as $i => $expected) {
            $this->assertSame($expected['label'], $builtins[$i]['label']);
            $this->assertSame($expected['path'], $builtins[$i]['path']);
            $this->assertSame($expected['category'], $builtins[$i]['category']);
            $this->assertSame('builtin', $builtins[$i]['source']);
            $this->assertSame([], $builtins[$i]['params_schema']);
            $this->assertFalse($builtins[$i]['external']);
        }

        $this->delete('/adminapi/diy/links/' . $created['id'], [], $admin->token)->assertCode(200);
    }

    /** label / path 传数组同样不能 500。 */
    public function test_array_values_are_rejected_with_422(): void
    {
        $admin = $this->actingAsAdmin('super');

        $response = $this->post('/adminapi/diy/links', ['label' => ['x'], 'path' => '/pages/index'], $admin->token);
        $this->assertSame(200, $response->status(), '不能是未捕获异常');
        $this->assertSame(422, $response->code());

        $response = $this->post('/adminapi/diy/links', ['label' => '链接', 'path' => ['x']], $admin->token);
        $this->assertSame(200, $response->status(), '不能是未捕获异常');
        $this->assertSame(422, $response->code());
    }

    /** path 只收站内绝对路径或 http(s)：javascript: 之流不该被当成站内链接存下来。 */
    public function test_path_shape_is_validated(): void
    {
        $admin = $this->actingAsAdmin('super');

        foreach (['javascript:alert(1)', '//evil.example.com', 'pages/index'] as $bad) {
            $this->post('/adminapi/diy/links', ['label' => '链接', 'path' => $bad], $admin->token)->assertCode(422);
        }
        $ok = $this->post('/adminapi/diy/links', ['label' => '链接', 'path' => '/pages/index/index'], $admin->token)->assertOk()->data();
        $this->track('diy_links', (int) $ok['id']);
    }
}
