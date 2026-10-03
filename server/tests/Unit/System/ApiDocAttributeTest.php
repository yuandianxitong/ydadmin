<?php

declare(strict_types=1);

namespace tests\Unit\System;

use app\adminapi\controller\system\ApiDocController;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;
use support\annotation\route\Route;

/** API 文档控制器不写任何路由属性：两条路由由 config/route.php 在 APP_DEBUG=true 时注册，生产环境不存在。 */
final class ApiDocAttributeTest extends TestCase
{
    public function test_api_doc_actions_have_no_path_attributes(): void
    {
        $ref = new ReflectionClass(ApiDocController::class);
        foreach ($ref->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }
            $this->assertCount(
                0,
                $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF),
                $method->getName() . ' 不得带路由属性'
            );
        }
        $this->assertCount(0, $ref->getAttributes(\support\annotation\route\RouteGroup::class), '类上不得带 RouteGroup');
    }
}
