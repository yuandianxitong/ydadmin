<?php

declare(strict_types=1);

namespace tests\Feature\Plugin;

use app\service\plugin\adapter\HostProfile;
use core\plugin\contracts\PluginHostProfile;
use support\Container;
use tests\Support\ConfigOverride;
use tests\TestCase;

final class HostProfileTest extends TestCase
{
    use ConfigOverride;

    protected function tearDown(): void
    {
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_names_admin_uniapp_and_pc_without_a_tenant_host(): void
    {
        $profile = new HostProfile();

        $this->assertSame(['admin', 'uniapp', 'pc'], $profile->sourceEnds());
        $this->assertSame(['admin', 'pc'], array_keys($profile->publishableFrontends()));
        $admin = $profile->publishableFrontends()['admin'];
        $this->assertSame('@yd/admin-host', $admin['prefix']);
        $this->assertSame('admin/build/host-sdk.ts', $admin['sdkSource']);
        $this->assertSame(
            ['vue', 'vue-router', 'pinia', 'vue-i18n', '@vueuse/core', 'element-plus', '@element-plus/icons-vue', 'axios'],
            $admin['vendors']
        );
        $this->assertSame(['http'], $admin['modules']);
        $pc = $profile->publishableFrontends()['pc'];
        $this->assertSame(['vue', 'pinia', 'naive-ui'], $pc['vendors']);
        $this->assertSame('@yd/pc-host', $pc['prefix']);
        $this->assertSame(['http'], $pc['modules']);
        $this->assertSame('pc/build/host-sdk.ts', $pc['sdkSource']);
    }

    public function test_missing_categories_config_returns_an_array(): void
    {
        $this->overrideConfig('plugin_host.categories', null);
        $categories = (new HostProfile())->categories();

        $this->assertIsArray($categories);
    }

    public function test_container_resolves_the_host_profile(): void
    {
        $this->assertInstanceOf(HostProfile::class, Container::get(PluginHostProfile::class));
    }
}
