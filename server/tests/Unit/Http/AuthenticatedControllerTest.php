<?php

declare(strict_types=1);

namespace tests\Unit\Http;

use ReflectionClass;
use ReflectionMethod;
use tests\TestCase;

final class AuthenticatedControllerTest extends TestCase
{
    public function test_admin_base_is_abstract_and_carries_the_auth_stack(): void
    {
        $ref = new ReflectionClass(\app\adminapi\controller\AuthenticatedController::class);
        $this->assertTrue($ref->isAbstract());
        $this->assertSame('core\base\Controller', $ref->getParentClass()->getName());
        $declared = array_filter(
            $ref->getMethods(),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $ref->getName()
        );
        $this->assertSame([], array_values($declared));
        $pairs = $ref->getAttributes(\support\annotation\Middleware::class)[0]->newInstance()->getMiddlewares();
        $this->assertSame([
            \app\middleware\AdminAuthMiddleware::class,
            \app\middleware\AdminPermissionMiddleware::class,
            \app\middleware\AdminLogMiddleware::class,
        ], array_column($pairs, 0));
    }

    public function test_api_base_is_abstract_and_carries_api_auth(): void
    {
        $ref = new ReflectionClass(\app\api\controller\AuthenticatedController::class);
        $this->assertTrue($ref->isAbstract());
        $this->assertSame('core\base\Controller', $ref->getParentClass()->getName());
        $declared = array_filter(
            $ref->getMethods(),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $ref->getName()
        );
        $this->assertSame([], array_values($declared));
        $pairs = $ref->getAttributes(\support\annotation\Middleware::class)[0]->newInstance()->getMiddlewares();
        $this->assertSame([
            \app\middleware\ApiAuthMiddleware::class,
        ], array_column($pairs, 0));
    }
}
