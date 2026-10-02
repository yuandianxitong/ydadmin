<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use tests\Support\ApiTestCase;

/** M6a spec §4.9 与设计决定 12：固定白名单，不按 is_public 动态放出。 */
final class CommonConfigApiTest extends ApiTestCase
{
    private const ALWAYS = ['site_name', 'site_url', 'site_logo', 'site_description', 'site_status', 'site_close_tip', 'user_register'];

    public function test_is_public_and_returns_exact_whitelist_without_open_app_id_when_unset(): void
    {
        $this->setConfig('wechat_open_app_id', '');

        $response = $this->get('/api/common/config');

        $response->assertOk();
        $data = $response->data();
        $this->assertSame(self::ALWAYS, array_keys($data), '键集合与顺序固定；未设置开放平台 appid 时不返回该键');
    }

    public function test_includes_open_app_id_when_set_and_never_leaks_secrets(): void
    {
        $this->setConfig('wechat_open_app_id', 'wx9f8e7d6c5b4a3210');
        $this->setConfig('wechat_open_app_secret', 'OPEN-SECRET-SHOULD-NEVER-LEAK');
        $this->setConfig('site_name', '测试站点');

        $response = $this->get('/api/common/config');

        $response->assertOk();
        $data = $response->data();
        $this->assertSame([...self::ALWAYS, 'wechat_open_app_id'], array_keys($data));
        $this->assertSame('wx9f8e7d6c5b4a3210', $data['wechat_open_app_id']);
        $this->assertSame('测试站点', $data['site_name']);
        $this->assertStringNotContainsString('OPEN-SECRET-SHOULD-NEVER-LEAK', $response->body());
        $this->assertStringNotContainsString('secret', strtolower((string) json_encode(array_keys($data))));
    }

    public function test_blank_open_app_id_is_treated_as_unset(): void
    {
        $this->setConfig('wechat_open_app_id', '   ');

        $this->assertArrayNotHasKey('wechat_open_app_id', $this->get('/api/common/config')->data());
    }

    public function test_other_public_configs_are_not_exposed(): void
    {
        // site_email 在 basic 组且 is_public=1，但不在白名单：日后误标公开的键也不会从这里漏出去
        $this->setConfig('site_email', 'ops@example.com');

        $this->assertStringNotContainsString('ops@example.com', $this->get('/api/common/config')->body());
    }
}
