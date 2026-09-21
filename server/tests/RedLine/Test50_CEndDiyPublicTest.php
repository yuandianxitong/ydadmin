<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;

/**
 * 红线（M7c）：C 端装修页与移动端配置对未登录公开。
 *
 * 未带 token 的 GET /api/mobile/diy-page?key=home、/api/mobile/config 不得 401。
 * 种子已发布 home 与配置均为业务成功 200。
 */
final class Test50_CEndDiyPublicTest extends ApiTestCase
{
    public function test_guest_can_read_published_home_and_mobile_config(): void
    {
        $home = $this->get('/api/mobile/diy-page', ['key' => 'home']);
        $this->assertNotSame(401, $home->code(), 'C 端装修页不得对未登录返回 401');
        $home->assertOk();

        $config = $this->get('/api/mobile/config');
        $this->assertNotSame(401, $config->code(), 'C 端移动端配置不得对未登录返回 401');
        $config->assertOk();
    }
}
