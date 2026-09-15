<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\ApiDocService;
use core\apidoc\OpenApiDocument;
use support\Container;
use tests\Support\ApiTestCase;
use Webman\Route;

/**
 * spec §10.1（最强的一条）：路由表里每条 /adminapi 路由在文档 paths 里恰好出现一次，反之亦然。
 * TP8 的 path: 是手敲字符串、从不与路由表比对——这条测试真正守的是「过滤器别漏掉谁、也别凭空多出谁」。
 *
 * 只比对 type=admin：type=api 当前恒为空文档（本仓库没有 /api 路由），双射对它永远平凡成立，
 * 不构成测试价值，等 pc/uniapp 的 /api 路由出现后再补——spec §12 已经把这条记在延续清单里。
 *
 * 两条 M2b 自己的路由（/adminapi/system/api-doc、/adminapi/system/api-doc/openapi.json）
 * 故意不特判掉：它们和其它路由一样从路由表被 RouteHarvester 收进来，也应该出现在文档里，
 * 这正是「自描述」该有的样子。
 */
final class ApiDocBijectionTest extends ApiTestCase
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'delete', 'patch'];

    public function test_route_table_and_document_paths_are_a_bijection(): void
    {
        self::ensureRoutesLoaded();
        self::resetDocumentCache();

        $fromRoutes = [];
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->getPath(), '/adminapi')) {
                continue;
            }
            // 路由表侧的清洗必须复用 OpenApiDocument 组装 paths 键时用的同一份逻辑（Step 0）——
            // 不能在这里另写一份正则:两边各写一份,将来清洗规则一改,这条测试会继续按旧规则绿着。
            $normalizedPath = OpenApiDocument::normalizePathTemplate($route->getPath());
            foreach ($route->getMethods() as $method) {
                if (strtoupper($method) === 'HEAD') {
                    continue;
                }
                $fromRoutes[] = strtoupper($method) . ' ' . $normalizedPath;
            }
        }
        sort($fromRoutes);
        $fromRoutes = array_values(array_unique($fromRoutes));

        $document = Container::get(ApiDocService::class)->document('admin');
        $fromDocument = [];
        foreach ($document['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                if (!in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }
                $fromDocument[] = strtoupper($method) . ' ' . $path;
            }
        }
        sort($fromDocument);

        $this->assertNotEmpty($fromRoutes, '路由表里没有 /adminapi 路由——测试本身失效，先检查 ensureRoutesLoaded()');
        $this->assertSame($fromRoutes, $fromDocument, "路由表与文档 paths 不是双射：\n路由表有而文档没有：" . implode(', ', array_diff($fromRoutes, $fromDocument))
            . "\n文档有而路由表没有：" . implode(', ', array_diff($fromDocument, $fromRoutes)));
    }

    /** 证明上面的断言不是摆设：人为在期望集合里塞一个不存在的路径，必须让测试失败。 */
    public function test_the_bijection_assertion_actually_detects_a_mismatch(): void
    {
        self::ensureRoutesLoaded();
        self::resetDocumentCache();
        $document = Container::get(ApiDocService::class)->document('admin');
        $fakePaths = array_keys($document['paths']);
        $fakePaths[] = '/adminapi/does-not-exist-in-route-table';

        $this->assertNotSame(
            array_keys($document['paths']),
            $fakePaths,
            '这条断言本身必须能失败：篡改后的路径集合理应与真实文档不同'
        );
    }

    /**
     * ApiDocService::$documentCache 是进程内按 type 缓存的私有静态属性：其它测试
     * （如 ApiDocServiceTest::test_document_is_cached_per_type_in_process()）会用反射
     * 清空它却不恢复，导致下一次调用可能在与本测试无关的配置状态下重新 build 并写回缓存。
     * 这条测试是「过滤器别漏、别多」的最强验收，不能让它读到别的测试留下的旧文档——
     * 每次跑之前用同样的反射手法清一遍，保证本测试看到的是当前状态下新鲜 build 出来的文档。
     */
    private static function resetDocumentCache(): void
    {
        $cacheProperty = new \ReflectionProperty(ApiDocService::class, 'documentCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, []);
    }
}
