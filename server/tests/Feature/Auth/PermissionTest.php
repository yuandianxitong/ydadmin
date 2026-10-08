<?php

declare(strict_types=1);

namespace tests\Feature\Auth;

use core\auth\Permission;
use core\auth\TokenManager;
use core\exception\AuthException;
use core\permission\PermissionCheckerInterface;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;

final class PermissionTest extends ApiTestCase
{
    private function permission(): Permission
    {
        return Container::get(Permission::class);
    }

    public function test_container_binds_the_interface_to_the_rbac_singleton(): void
    {
        $this->assertSame($this->permission(), Container::get(PermissionCheckerInterface::class));
        $this->assertFileDoesNotExist(base_path() . '/core/permission/DenyAllChecker.php');
    }

    public function test_super_admin_has_star_first_and_every_permission(): void
    {
        $admin = $this->actingAsAdmin('super');

        $this->assertTrue($this->permission()->isSuperAdmin($admin->id));
        $permissions = $this->permission()->getUserPermissions($admin->id);
        $this->assertSame('*', $permissions[0]);
        $this->assertContains('system.menu.delete', $permissions);
        $this->assertTrue($this->permission()->check($admin->id, 'anything.at.all'));
    }

    public function test_regular_admin_only_gets_granted_permissions(): void
    {
        $admin = $this->actingAsAdmin(['system.admin.list', 'system.admin.create']);

        $this->assertFalse($this->permission()->isSuperAdmin($admin->id));
        $this->assertEqualsCanonicalizing(['system.admin.list', 'system.admin.create'], $this->permission()->getUserPermissions($admin->id));
        $this->assertTrue($this->permission()->check($admin->id, 'system.admin.create'));
        $this->assertFalse($this->permission()->check($admin->id, 'system.admin.delete'));
    }

    public function test_disabled_menu_or_role_revokes_after_cache_clear(): void
    {
        $admin = $this->actingAsAdmin(['system.admin.list', 'system.role.list']);
        $roleId = (int) Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
        $this->assertTrue($this->permission()->check($admin->id, 'system.role.list'));

        Db::table('menus')->where('permission', 'system.role.list')->update(['status' => 0]);
        try {
            $this->assertTrue($this->permission()->check($admin->id, 'system.role.list'), '清缓存前仍是旧结果');
            $this->permission()->clearUserCache($admin->id);
            $this->assertFalse($this->permission()->check($admin->id, 'system.role.list'), '禁用的菜单不再授予权限');
        } finally {
            Db::table('menus')->where('permission', 'system.role.list')->update(['status' => 1]);
        }

        Db::table('roles')->where('id', $roleId)->update(['status' => 0]);
        $this->permission()->clearUserCache($admin->id);
        $this->assertSame([], $this->permission()->getUserPermissions($admin->id), '禁用的角色不再授予任何权限');
    }

    /** 吊销失效（如 Redis 丢了 token 版本号）时的兜底：被禁用的管理员哪怕 token 还能过校验，也拿不到任何权限。 */
    public function test_disabled_admin_gets_no_permissions(): void
    {
        $admin = $this->actingAsAdmin(['system.admin.list']);
        $this->assertTrue($this->permission()->check($admin->id, 'system.admin.list'));

        Db::table('admins')->where('id', $admin->id)->update(['status' => 0]);
        $this->permission()->clearUserCache($admin->id);

        $this->assertFalse($this->permission()->check($admin->id, 'system.admin.list'));
        $this->assertSame([], $this->permission()->getUserPermissions($admin->id));
    }

    public function test_deleted_super_admin_is_no_longer_super(): void
    {
        $admin = $this->actingAsAdmin('super');
        $this->assertTrue($this->permission()->isSuperAdmin($admin->id));

        Db::table('admins')->where('id', $admin->id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->permission()->clearUserCache($admin->id);

        $this->assertFalse($this->permission()->isSuperAdmin($admin->id));
        $this->assertFalse($this->permission()->check($admin->id, 'system.admin.list'));
    }

    public function test_clear_all_only_removes_permission_keys(): void
    {
        $admin = $this->actingAsAdmin(['system.admin.list']);
        $epoch = (int) (Redis::get('perm.epoch') ?: 0);
        $userEpoch = (int) (Redis::get('perm.user.' . $admin->id) ?: 0);
        $this->permission()->check($admin->id, 'system.admin.list');

        $mgr = TokenManager::scope('admin');
        $token = $mgr->generate(['admin_id' => $admin->id, 'username' => $admin->username, 'ver' => \core\auth\TokenVersion::current($admin->id)]);
        $mgr->blacklist($token);

        $this->permission()->clearAllCache();
        Redis::setEx("perm.list.{$admin->id}.{$epoch}.{$userEpoch}", 60, (string) json_encode(['planted.permission']));

        $this->assertNotContains('planted.permission', $this->permission()->getUserPermissions($admin->id));
        try {
            $mgr->verify($token);
            $this->fail('clearAllCache 不得清掉 token 黑名单');
        } catch (AuthException) {
            $this->addToAssertionCount(1);
        }
    }
}
