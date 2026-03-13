<?php
declare(strict_types=1);

namespace tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use core\auth\Permission;

class PermissionTest extends TestCase
{
    /**
     * 超级管理员(ID=1)应拥有所有权限
     */
    public function testSuperAdminHasAllPermissions(): void
    {
        $permission = new Permission();
        $this->assertTrue($permission->check(1, 'system.admin.list'));
        $this->assertTrue($permission->check(1, 'any.permission.here'));
        $this->assertTrue($permission->check(1, 'non.existent.permission'));
    }

    /**
     * 超级管理员角色检查
     */
    public function testSuperAdminRoleCheck(): void
    {
        // hasRole depends on DB, but we can test the super_admin check concept
        $permission = $this->getMockBuilder(Permission::class)
            ->onlyMethods(['getUserRoles'])
            ->getMock();

        $permission->method('getUserRoles')
            ->willReturn(['super_admin']);

        $this->assertTrue($permission->hasRole(1, 'any_role'));
    }

    /**
     * 普通用户有指定权限时应通过
     */
    public function testUserWithPermissionPasses(): void
    {
        $permission = $this->getMockBuilder(Permission::class)
            ->onlyMethods(['getUserPermissions'])
            ->getMock();

        $permission->method('getUserPermissions')
            ->willReturn(['system.admin.list', 'system.admin.create']);

        $this->assertTrue($permission->check(2, 'system.admin.list'));
        $this->assertTrue($permission->check(2, 'system.admin.create'));
    }

    /**
     * 普通用户无指定权限时应拒绝
     */
    public function testUserWithoutPermissionFails(): void
    {
        $permission = $this->getMockBuilder(Permission::class)
            ->onlyMethods(['getUserPermissions'])
            ->getMock();

        $permission->method('getUserPermissions')
            ->willReturn(['system.admin.list']);

        $this->assertFalse($permission->check(2, 'system.admin.delete'));
    }

    /**
     * 拥有通配符权限的用户应拥有所有权限
     */
    public function testWildcardPermissionGrantsAll(): void
    {
        $permission = $this->getMockBuilder(Permission::class)
            ->onlyMethods(['getUserPermissions'])
            ->getMock();

        $permission->method('getUserPermissions')
            ->willReturn(['*']);

        $this->assertTrue($permission->check(2, 'any.permission'));
    }

    /**
     * 权限中间件应返回闭包
     */
    public function testMiddlewareReturnsClosure(): void
    {
        $permission = new Permission();
        $middleware = $permission->middleware('system.admin.list');

        $this->assertInstanceOf(\Closure::class, $middleware);
    }
}
