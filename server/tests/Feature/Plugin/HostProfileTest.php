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

    public function test_host_only_advertises_implemented_frontends(): void
    {
        $profile = new HostProfile();
        $this->assertSame('framework-webman', $profile->hostId());
        $this->assertSame(['admin'], $profile->sourceEnds());
        $this->assertSame(['admin'], array_keys($profile->publishableFrontends()));
        $this->assertSame(['http', 'i18n', 'router', 'store'], $profile->publishableFrontends()['admin']['modules']);
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
