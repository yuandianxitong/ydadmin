<?php

declare(strict_types=1);

namespace tests\Unit\ApiDoc;

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use ReflectionProperty;
use tests\Support\ConfigOverride;
use tests\TestCase;
use Webman\Route;

/** 文档路由在两种模式都注册；权限与认证由控制器继承的管理端栈验证。 */
final class ApiDocProductionGateTest extends TestCase
{
    use ConfigOverride;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::ensureRoutesLoaded();
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_api_doc_routes_are_present_when_debug_is_false(): void
    {
        $paths = $this->registerRoutesWithDebug(false);

        $this->assertContains('GET /adminapi/system/api-doc', $paths);
        $this->assertContains('GET /adminapi/system/api-doc/openapi.json', $paths);
        $this->assertContains('GET /', $paths);
    }

    public function test_api_doc_routes_are_present_when_debug_is_true(): void
    {
        $paths = $this->registerRoutesWithDebug(true);

        $this->assertContains('GET /adminapi/system/api-doc', $paths);
        $this->assertContains('GET /adminapi/system/api-doc/openapi.json', $paths);
    }

    /**
     * @return list<string> "METHOD /path" 形式的路由列表
     *
     * Route::load() 每次加载前会重置 $allRoutes/$methodPathIndex（它们与 $collector 是三个
     * 独立的静态属性，webman 源码 Route.php:571-572）。这里不走 load()（会用 require_once，
     * 第二次调用不重新执行文件），只手工 require，所以要把这两个属性也当成 $collector 一样
     * 快照/清空/还原：本方法在同一进程里会被两个测试方法各调用一次，都 require 同一份
     * config/route.php，若不清空这两个属性，第二次调用会把第一次注册的路由当成「已存在」
     * 报 Route conflict。
     */
    private function registerRoutesWithDebug(bool $debug): array
    {
        $this->overrideConfig('app.debug', $debug);

        $collectorProperty = new ReflectionProperty(Route::class, 'collector');
        $collectorProperty->setAccessible(true);
        $original = $collectorProperty->getValue();

        $allRoutesProperty = new ReflectionProperty(Route::class, 'allRoutes');
        $allRoutesProperty->setAccessible(true);
        $originalAllRoutes = $allRoutesProperty->getValue();

        $methodPathIndexProperty = new ReflectionProperty(Route::class, 'methodPathIndex');
        $methodPathIndexProperty->setAccessible(true);
        $originalMethodPathIndex = $methodPathIndexProperty->getValue();

        Route::setCollector(new RouteCollector(new Std(), new GroupCountBased()));
        $allRoutesProperty->setValue(null, []);
        $methodPathIndexProperty->setValue(null, []);

        try {
            require config_path() . '/route.php';

            $paths = [];
            foreach (Route::getRoutes() as $route) {
                foreach ($route->getMethods() as $method) {
                    $paths[] = strtoupper($method) . ' ' . $route->getPath();
                }
            }

            return $paths;
        } finally {
            $collectorProperty->setValue(null, $original);
            $allRoutesProperty->setValue(null, $originalAllRoutes);
            $methodPathIndexProperty->setValue(null, $originalMethodPathIndex);
        }
    }
}
