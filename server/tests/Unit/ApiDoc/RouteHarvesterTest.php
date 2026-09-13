<?php

declare(strict_types=1);

namespace tests\Unit\ApiDoc;

use app\adminapi\controller\system\DictionaryController;
use core\apidoc\EndpointDescriptor;
use core\apidoc\RouteHarvester;
use ReflectionProperty;
use tests\TestCase;
use Webman\Route;

final class RouteHarvesterTest extends TestCase
{
    public function test_harvest_filters_by_prefix_reads_attributes_and_sorts_deterministically(): void
    {
        self::ensureRoutesLoaded();

        $harvester = new RouteHarvester('/adminapi');
        $endpoints = $harvester->harvest();

        $this->assertNotEmpty($endpoints);
        foreach ($endpoints as $endpoint) {
            $this->assertStringStartsWith('/adminapi', $endpoint->path, 'harvest() 必须按前缀过滤，不能带出 SPA/fallback 路由');
        }

        // /adminapi/system/dictionary/{id:\d+} 上挂了 show(GET)/update(PUT)/delete(DELETE) 三个方法，
        // 是验证「按 path 再按 method 稳定排序」的现成例子。
        $samePath = array_values(array_filter(
            $endpoints,
            static fn (EndpointDescriptor $e): bool => $e->path === '/adminapi/system/dictionary/{id:\d+}'
        ));
        $this->assertSame(
            ['DELETE', 'GET', 'PUT'],
            array_map(static fn (EndpointDescriptor $e): string => $e->method, $samePath),
            '同路径下必须按方法字母序稳定排序'
        );

        $show = null;
        foreach ($endpoints as $endpoint) {
            if ($endpoint->path === '/adminapi/system/dictionary/{id:\d+}' && $endpoint->method === 'GET') {
                $show = $endpoint;
            }
        }
        $this->assertNotNull($show);
        $this->assertSame(DictionaryController::class, $show->controller);
        $this->assertSame('show', $show->action);
        $this->assertSame('system.dictionary.list', $show->permission);
        $this->assertFalse($show->permissionSkipped);
        $this->assertSame('system', $show->tag);

        $options = null;
        foreach ($endpoints as $endpoint) {
            if ($endpoint->path === '/adminapi/system/dictionary/options' && $endpoint->method === 'GET') {
                $options = $endpoint;
            }
        }
        $this->assertNotNull($options, '#[PermissionSkip] 的端点也必须被收集');
        $this->assertNull($options->permission);
        $this->assertTrue($options->permissionSkipped);
        $this->assertSame('system', $options->tag);

        foreach ($endpoints as $endpoint) {
            $this->assertNotSame('/', $endpoint->path, 'SPA 路由不应带出');
            $this->assertNotSame('/admin[/{path:.*}]', $endpoint->path, 'SPA 路由不应带出');
        }
    }

    /**
     * $path 必须原样保留路由里的正则（`{id:\d+}`），不能清洗成 `{id}`——pathParameters() 靠这段
     * `\d+` 才能把类型判成 integer，清洗成 OpenAPI 路径模板是 OpenApiDocument（Task 4）的职责，
     * 不是 RouteHarvester 的。这条测试钉死这个边界：谁在这里加清洗，它就红。
     */
    public function test_path_preserves_the_raw_route_regex_without_normalization(): void
    {
        self::ensureRoutesLoaded();

        $harvester = new RouteHarvester('/adminapi');
        $endpoints = $harvester->harvest();

        $show = null;
        foreach ($endpoints as $endpoint) {
            if ($endpoint->controller === DictionaryController::class && $endpoint->action === 'show') {
                $show = $endpoint;
            }
        }

        $this->assertNotNull($show);
        $this->assertSame('/adminapi/system/dictionary/{id:\d+}', $show->path, '路径必须原样带着 \d+，不能清洗成 {id}');
        $this->assertSame([['name' => 'id', 'type' => 'integer']], $show->pathParameters());
    }

    public function test_skips_non_array_callback_and_records_reason(): void
    {
        self::ensureRoutesLoaded();

        $allRoutesProperty = new ReflectionProperty(Route::class, 'allRoutes');
        $allRoutesProperty->setAccessible(true);
        $methodPathIndexProperty = new ReflectionProperty(Route::class, 'methodPathIndex');
        $methodPathIndexProperty->setAccessible(true);

        $originalRoutes = $allRoutesProperty->getValue();
        $originalIndex = $methodPathIndexProperty->getValue();

        try {
            Route::get('/adminapi/__apidoc_harvester_test_closure', function () {
                return 'unused';
            });

            $harvester = new RouteHarvester('/adminapi');
            $endpoints = $harvester->harvest();

            foreach ($endpoints as $endpoint) {
                $this->assertNotSame(
                    '/adminapi/__apidoc_harvester_test_closure',
                    $endpoint->path,
                    '非 [控制器类, 动作] 数组的 callback 绝不能被猜成一个端点'
                );
            }

            $skipped = $harvester->skipped();
            $this->assertCount(1, $skipped);
            $this->assertStringContainsString('/adminapi/__apidoc_harvester_test_closure', $skipped[0]);
            $this->assertStringContainsString('GET', $skipped[0]);
        } finally {
            $allRoutesProperty->setValue(null, $originalRoutes);
            $methodPathIndexProperty->setValue(null, $originalIndex);
        }
    }
}
