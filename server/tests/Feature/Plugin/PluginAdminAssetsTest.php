<?php

declare(strict_types=1);

namespace tests\Feature\Plugin;

use app\service\plugin\PluginService;
use core\auth\TokenManager;
use core\plugin\PluginAssetPublisher;
use core\plugin\PluginFiles;
use core\plugin\PluginPaths;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;

final class PluginAdminAssetsTest extends ApiTestCase
{
    use ConfigOverride;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideConfig('auth.jwt.admin.key', bin2hex(random_bytes(32)));
        TokenManager::flushInstances();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->restoreConfig();
            TokenManager::flushInstances();
        }
    }

    public function test_site_plugin_publishes_an_authenticated_admin_page_and_manages_its_menu(): void
    {
        $code = 'admin-asset-' . bin2hex(random_bytes(4));
        $dir = PluginPaths::dir($code);
        $page = 'plugins/' . $code . '/index';
        $folder = $dir . '/dist/admin';
        mkdir($folder . '/js', 0755, true);
        mkdir($dir . '/app', 0755, true);
        file_put_contents($dir . '/plugin.json', json_encode([
            'code' => $code,
            'name' => 'Page probe',
            'version' => '1.0.0',
            'kind' => 'plugin',
            'hosts' => ['framework-webman'],
            'psr4' => ['Plugin\\AdminAssetProbe\\' => 'app/'],
            'lifecycle' => 'Plugin\\AdminAssetProbe\\Lifecycle',
            'menus' => [['title' => 'Page probe', 'permission' => $code . '.list', 'path' => '/' . $code, 'component' => $page]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($dir . '/app/Lifecycle.php', <<<'PHP_PLUGIN'
<?php
namespace Plugin\AdminAssetProbe;

final class Lifecycle implements \core\plugin\contracts\LifecycleHook
{
    public function install(): void {}
    public function uninstall(): void {}
    public function upgrade(string $from, string $to): void {}
}
PHP_PLUGIN);
        file_put_contents($folder . '/js/index.js', "import { h } from 'vue'; export default { render: () => h('p', 'Page probe') }");
        file_put_contents($folder . '/manifest.json', json_encode([
            'schema' => 1,
            'host' => 'admin',
            'code' => $code,
            'version' => '1.0.0',
            'hostSdk' => '^1.0.0',
            'externals' => ['vue'],
            'pages' => [$page => 'js/index.js'],
        ], JSON_THROW_ON_ERROR));

        try {
            $service = Container::get(PluginService::class);
            $service->install($code);
            $menuId = Db::table('plugin_menus')->where('plugin_code', $code)->value('menu_id');
            $this->assertSame($page, Db::table('menus')->where('id', $menuId)->value('component'));

            $this->get('/adminapi/plugin/assets')->assertCode(401);
            $admin = $this->actingAsAdmin([]);
            $entries = $this->get('/adminapi/plugin/assets', [], $admin->token)->assertOk()->data();
            $this->assertSame([$page => 'js/index.js'], array_column($entries, null, 'code')[$code]['pages']);
            $this->assertFileExists(PluginAssetPublisher::publicDir(base_path(), 'admin', $code) . '/js/index.js');

            $service->disable($code);
            $entries = $this->get('/adminapi/plugin/assets', [], $admin->token)->assertOk()->data();
            $this->assertArrayNotHasKey($code, array_column($entries, null, 'code'));
            $this->assertSame(0, (int) Db::table('menus')->where('id', $menuId)->value('status'));

            $service->enable($code);
            $this->assertSame(1, (int) Db::table('menus')->where('id', $menuId)->value('status'));
            $service->uninstall($code);
            $this->assertFalse(Db::table('menus')->where('id', $menuId)->exists());
        } finally {
            $ids = Db::table('plugin_menus')->where('plugin_code', $code)->pluck('menu_id')->all();
            Db::table('plugin_menus')->where('plugin_code', $code)->delete();
            Db::table('menus')->whereIn('id', $ids)->delete();
            Db::table('plugin_sagas')->where('code', $code)->delete();
            Db::table('plugins')->where('code', $code)->delete();
            PluginAssetPublisher::remove(base_path(), 'admin', $code);
            PluginFiles::removeTree($dir);
        }
    }
}
