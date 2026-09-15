<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\ApiDocService;
use support\Container;
use tests\TestCase;

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

    public function test_document_is_cached_per_type_in_process(): void
    {
        $service = Container::get(ApiDocService::class);
        $first = $service->document('admin');
        $second = $service->document('admin');

        $this->assertSame($first, $second, '同一 type 的文档应命中进程内缓存，返回同一份数组内容');
    }
}
