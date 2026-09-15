<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\ApiDocService;
use core\apidoc\OpenApiDocument;
use ReflectionProperty;
use support\Container;
use tests\TestCase;
use Webman\Route;
use Webman\Route\Route as RouteObject;

final class ApiDocServiceTest extends TestCase
{
    /** RouteHarvester 需要活的路由表：与 RouteTest 同一手法，先确保路由已加载。 */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::ensureRoutesLoaded();
    }

    public function test_admin_document_has_a_legal_openapi_envelope_with_non_empty_paths(): void
    {
        $document = Container::get(ApiDocService::class)->document('admin');

        $this->assertSame('3.0.3', $document['openapi']);
        $this->assertIsArray($document['info']);
        $this->assertIsArray($document['servers']);
        $this->assertIsArray($document['paths']);
        $this->assertNotSame([], $document['paths'], '/adminapi 下真实有路由，paths 不该是空的');
        $this->assertArrayHasKey('/adminapi/system/dictionary/{id}', $document['paths'], '路径参数必须归一化成 {id}，不能带 webman 的 :\\d+ 正则片段');
    }

    public function test_unknown_type_falls_back_to_a_legal_empty_document(): void
    {
        $document = Container::get(ApiDocService::class)->document('does-not-exist');

        $this->assertSame('3.0.3', $document['openapi']);
        $this->assertIsArray($document['info']);
        $this->assertIsArray($document['servers']);
        $this->assertSame([], $document['paths'], "未知 type 按 'api' 处理：本仓库当前没有 /api 路由，必须是合法空文档");
    }

    /**
     * 只比较两次调用的返回值相等（旧版本）没有判别力：build() 是确定性纯函数，就算完全不缓存、
     * 每次都重新跑一遍 harvest + reflect + build，两次返回的数组内容也会相等，assertSame 一样
     * 会绿。改用反射直接看私有静态 $documentCache：调用前该 type 的键不存在，调用后存在——
     * 谁把缓存逻辑删了（哪怕换成每次都重新 build），这条断言会先红。
     */
    public function test_document_is_cached_per_type_in_process(): void
    {
        $service = Container::get(ApiDocService::class);
        $cacheProperty = new ReflectionProperty(ApiDocService::class, 'documentCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, []);

        $this->assertArrayNotHasKey('admin', $cacheProperty->getValue(), '调用前不应该已经有缓存条目');

        $first = $service->document('admin');
        $this->assertArrayHasKey('admin', $cacheProperty->getValue(), '调用后必须把结果写进静态缓存');

        $second = $service->document('admin');
        $this->assertSame($first, $second, '同一 type 的文档应命中进程内缓存，返回同一份数组内容');
    }

    /**
     * OpenAPI 客户端按 server.url + path key 拼请求 URL。servers[0].url 必须是 '/'
     * （不能是 /adminapi 这个前缀本身），因为 path key 已经带着完整前缀——否则拼出
     * '/adminapi/adminapi/...' 这种不存在的路径，Swagger UI 的 Try it out 全部 404。
     * 拼接规则用 rtrim(serverUrl, '/') . path：serverUrl 是 '/' 时 rtrim 后是空串，
     * 避免拼出 '//adminapi/...' 的双斜杠。
     */
    public function test_server_url_concatenates_with_a_path_key_into_a_real_route(): void
    {
        $document = Container::get(ApiDocService::class)->document('admin');

        $serverUrl = $document['servers'][0]['url'];
        $pathKey = '/adminapi/system/dictionary/{id}';
        $this->assertArrayHasKey($pathKey, $document['paths']);

        $requestUrl = rtrim($serverUrl, '/') . $pathKey;

        $realPaths = array_values(array_unique(array_map(
            static fn (RouteObject $route): string => OpenApiDocument::normalizePathTemplate($route->getPath()),
            array_filter(
                Route::getRoutes(),
                static fn (RouteObject $route): bool => str_starts_with($route->getPath(), '/adminapi')
            )
        )));

        $this->assertContains(
            $requestUrl,
            $realPaths,
            "servers[0].url('{$serverUrl}') 与 path key('{$pathKey}') 拼接成 '{$requestUrl}'，必须命中路由表里真实存在的路径模板"
        );
    }
}
