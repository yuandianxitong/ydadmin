<?php

declare(strict_types=1);

namespace tests\RedLine;

use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\ApiTestCase;

/**
 * 红线（M6c spec §1、§6）：管理端公众号运营面只暴露自定义菜单。粉丝列表、用户信息、模板消息发送
 * 三条 1.x 接口**不注册**（HTTP 404 + 统一 JSON `messages.api_not_found`），不是「注册了但加权限」。
 * 模板发送走 M6b 消息通道；粉丝与用户资料不在本里程碑打开 HTTP 面。
 */
final class Test40_OfficialOpsSurfaceNotExposedTest extends ApiTestCase
{
    /** @return array<string, array{string, string}> */
    public static function forbiddenEndpoints(): array
    {
        return [
            'GET followers'      => ['GET', '/adminapi/wechat/official/followers'],
            'POST followers'     => ['POST', '/adminapi/wechat/official/followers'],
            'GET user-info'      => ['GET', '/adminapi/wechat/official/user-info'],
            'POST user-info'     => ['POST', '/adminapi/wechat/official/user-info'],
            'GET template/send'  => ['GET', '/adminapi/wechat/official/template/send'],
            'POST template/send' => ['POST', '/adminapi/wechat/official/template/send'],
        ];
    }

    #[DataProvider('forbiddenEndpoints')]
    public function test_followers_user_info_and_template_send_are_not_routed(string $method, string $uri): void
    {
        $admin = $this->actingAsAdmin();
        $response = $this->call($method, $uri, [], $admin->token);

        $this->assertSame(404, $response->status(), "{$method} {$uri} 必须落到 fallback（HTTP 404），不能被注册");
        $response->assertCode(404);
    }
}
