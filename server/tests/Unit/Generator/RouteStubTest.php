<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use app\middleware\AdminAuthMiddleware;
use core\generator\TemplateRenderer;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use ReflectionProperty;
use tests\TestCase;
use Webman\Route;

/**
 * route.stub.php 的黄金测试 + 路由可达性测试。
 *
 * 路由可达性测试不走 TestCase::ensureRoutesLoaded()/Route::load()：那条路径全程用 require_once，
 * 进程内只能真正执行一次 config/route.php，本测试如果也调它会把全局路由表清空且不重建
 * （TestCase::ensureRoutesLoaded() 的类注释已经点出这个坑），殃及其它所有接口测试。
 * 因此这里直接操作 Webman\Route 的静态 collector：临时换一个全新 RouteCollector，
 * require 生成的路由文件（此时 Route::group()/get()/post()/... 会写进这个临时 collector），
 * 断言完再把 collector 换回来，不影响同进程里其它测试用到的真实路由表。
 */
final class RouteStubTest extends TestCase
{
    use GenArticleFixtureColumns;

    public function test_renders_byte_identical_to_golden_fixture(): void
    {
        $renderer = new TemplateRenderer(base_path() . '/core/generator/stubs');
        $content = $renderer->render('route.stub.php', self::genArticleVars());

        $expected = file_get_contents(base_path() . '/tests/fixtures/generated/config/route/demo.php');
        $this->assertSame($expected, $content);
    }

    public function test_generated_route_file_registers_expected_routes_under_admin_auth(): void
    {
        $renderer = new TemplateRenderer(base_path() . '/core/generator/stubs');
        $content = $renderer->render('route.stub.php', self::genArticleVars());

        $tmpFile = tempnam(sys_get_temp_dir(), 'gen_route_') . '.php';
        file_put_contents($tmpFile, $content);

        $collectorProperty = new ReflectionProperty(Route::class, 'collector');
        $collectorProperty->setAccessible(true);
        $originalCollector = $collectorProperty->getValue();

        Route::setCollector(new RouteCollector(new Std(), new GroupCountBased()));

        try {
            $adminAuth = [AdminAuthMiddleware::class];
            (function () use ($tmpFile, $adminAuth): void {
                require $tmpFile;
            })();

            $matched = array_values(array_filter(
                Route::getRoutes(),
                static fn ($route): bool => str_starts_with($route->getPath(), '/demo/gen-article')
            ));

            $expected = [
                ['GET', '/demo/gen-article'],
                ['POST', '/demo/gen-article/batch-delete'],
                ['PUT', '/demo/gen-article/{id:\d+}/status'],
                ['GET', '/demo/gen-article/{id:\d+}'],
                ['POST', '/demo/gen-article'],
                ['PUT', '/demo/gen-article/{id:\d+}'],
                ['DELETE', '/demo/gen-article/{id:\d+}'],
            ];

            $this->assertCount(count($expected), $matched, '生成的路由数量与预期不符（含 status 端点）');
            foreach ($expected as $i => [$method, $path]) {
                $this->assertSame([$method], $matched[$i]->getMethods(), "第 {$i} 条路由方法不符");
                $this->assertSame($path, $matched[$i]->getPath(), "第 {$i} 条路由路径不符");
                $this->assertContains(
                    AdminAuthMiddleware::class,
                    $matched[$i]->middleware(),
                    "第 {$i} 条路由未挂 \$adminAuth 组（AdminAuthMiddleware 缺失）"
                );
            }
        } finally {
            unlink($tmpFile);
            $collectorProperty->setValue(null, $originalCollector);
        }
    }
}
