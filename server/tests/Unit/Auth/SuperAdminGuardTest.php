<?php

declare(strict_types=1);

namespace tests\Unit\Auth;

use core\auth\SuperAdminGuard;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\permission\PermissionCheckerInterface;
use support\Container;
use tests\TestCase;

final class SuperAdminGuardTest extends TestCase
{
    /** @param list<int> $superAdmins */
    private function guard(array $superAdmins): SuperAdminGuard
    {
        return new SuperAdminGuard(new class ($superAdmins) implements PermissionCheckerInterface {
            /** @param list<int> $superAdmins */
            public function __construct(private readonly array $superAdmins)
            {
            }

            public function isSuperAdmin(int $adminId): bool
            {
                return in_array($adminId, $this->superAdmins, true);
            }

            public function check(int $adminId, string $permission): bool
            {
                return true; // 权限点再全也不等于超管
            }
        });
    }

    public function test_cli_without_acting_user_is_exempt(): void
    {
        $this->guard([])->assert();
        $this->addToAssertionCount(1);
    }

    public function test_super_admin_passes(): void
    {
        RequestContext::setActingUser(5);
        $this->guard([5])->assert();
        $this->addToAssertionCount(1);
    }

    public function test_non_super_is_rejected_even_with_every_permission(): void
    {
        RequestContext::setActingUser(7);

        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage(lang('auth.super_admin_only'));
        $this->guard([5])->assert();
    }

    public function test_container_autowires_a_shared_instance(): void
    {
        $guard = Container::get(SuperAdminGuard::class);

        $this->assertInstanceOf(SuperAdminGuard::class, $guard);
        $this->assertSame($guard, Container::get(SuperAdminGuard::class));
    }
}
