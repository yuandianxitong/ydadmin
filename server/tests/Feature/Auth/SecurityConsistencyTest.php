<?php

declare(strict_types=1);

namespace tests\Feature\Auth;

use app\repository\system\AdminRepository;
use app\repository\user\UserRepository;
use app\service\system\AdminService;
use app\service\system\RoleService;
use app\service\user\UserAuthService;
use core\auth\AuthorizationVersion;
use core\auth\PermissionChecker;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\datascope\DataScopeResolver;
use core\exception\AuthException;
use core\exception\BusinessException;
use support\Cache;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

final class SecurityConsistencyTest extends ApiTestCase
{
    public function test_permission_revocation_survives_a_failed_after_commit_callback(): void
    {
        $super = $this->actingAsAdmin('super');
        $admin = $this->actingAsAdmin(['system.admin.list']);
        $roleId = (int) Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
        $this->get('/adminapi/system/admin', [], $admin->token)->assertOk();
        $before = AuthorizationVersion::current();
        $service = Container::get(RoleService::class);
        $property = new \ReflectionProperty(RoleService::class, 'permission');
        $original = $property->getValue($service);
        (function (): void {
            unset($this->permission);
        })->call($service);
        try {
            $response = $this->put('/adminapi/system/role/' . $roleId . '/assign-permissions', ['menu_ids' => []], $super->token);
            $this->assertSame(500, $response->status());
        } finally {
            $property->setValue($service, $original);
        }
        $this->assertGreaterThan($before, AuthorizationVersion::current());
        $this->assertSame(0, Db::table('role_menus')->where('role_id', $roleId)->count());
        $this->get('/adminapi/system/admin', [], $admin->token)->assertCode(403);
    }

    public function test_data_scope_changes_survive_missing_redis_invalidation(): void
    {
        $admin = $this->actingAsAdmin();
        $resolver = Container::get(DataScopeResolver::class);
        $this->assertTrue($resolver->resolve($admin->id)->all);
        Db::connection()->transaction(static function () use ($admin): void {
            AuthorizationVersion::bump();
            $roleId = Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
            Db::table('roles')->where('id', $roleId)->update(['data_scope' => 4]);
        });
        $snapshot = $resolver->resolve($admin->id);
        $this->assertFalse($snapshot->all);
        $this->assertTrue($snapshot->self);
    }

    public function test_authorization_version_rolls_back_with_the_write(): void
    {
        $before = AuthorizationVersion::current();
        try {
            Db::connection()->transaction(static function (): void {
                AuthorizationVersion::bump();
                throw new \RuntimeException('rollback');
            });
            $this->fail('事务应回滚');
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback', $e->getMessage());
        }
        $this->assertSame($before, AuthorizationVersion::current());
    }

    public function test_authorization_version_cannot_advance_outside_a_transaction(): void
    {
        $this->expectException(\LogicException::class);
        AuthorizationVersion::bump();
    }

    public function test_logout_stays_revoked_after_redis_markers_are_deleted(): void
    {
        $admin = $this->actingAsAdmin();
        $sid = bin2hex(random_bytes(16));
        $manager = TokenManager::scope('admin');
        $token = $manager->generate(['admin_id' => $admin->id, 'ver' => TokenVersion::current($admin->id), 'sid' => $sid]);
        $jti = $manager->verifyClaims($token)['jti'];
        $this->post('/adminapi/auth/logout', [], $token)->assertOk();
        Cache::delete('jwt_blacklist_admin_' . $jti);
        Cache::delete('jwt_session_revoked_admin_' . $sid);
        $this->assertTrue($manager->isJtiRevoked($jti));
        $this->assertTrue($manager->isSessionRevoked($sid));
        $this->assertFalse(TokenManager::scope('user')->isJtiRevoked($jti));
        $this->assertFalse(TokenManager::scope('user')->isSessionRevoked($sid));
        $this->get('/adminapi/auth/info', [], $token)->assertCode(401);
        $this->post('/adminapi/auth/refresh', [], $token)->assertCode(401);
    }

    public function test_user_logout_stays_revoked_after_redis_marker_is_deleted(): void
    {
        $user = $this->actingAsUser();
        $manager = TokenManager::scope('user');
        $jti = $manager->verifyClaims($user->token)['jti'];
        $this->post('/api/auth/logout', [], $user->token)->assertOk();
        Cache::delete('jwt_blacklist_user_' . $jti);
        $this->assertTrue($manager->isJtiRevoked($jti));
        $this->get('/api/user/profile', [], $user->token)->assertCode(401);
    }

    public function test_admin_password_snapshot_cannot_receive_a_new_token_version(): void
    {
        $admin = $this->actingAsAdmin();
        $service = Container::get(AdminService::class);
        $property = new \ReflectionProperty(AdminService::class, 'adminRepository');
        $original = $property->getValue($service);
        $property->setValue($service, new class () extends AdminRepository {
            public function findByUsername(string $username): ?array
            {
                $snapshot = parent::findByUsername($username);
                if ($snapshot !== null) {
                    Db::connection()->transaction(static function () use ($snapshot): void {
                        Db::table('admins')->where('id', $snapshot['id'])->update(['password' => password_hash('Replacement#2026', PASSWORD_DEFAULT)]);
                        TokenVersion::bump((int) $snapshot['id']);
                    });
                }

                return $snapshot;
            }
        });
        try {
            $this->expectException(BusinessException::class);
            $service->login($admin->username, $admin->password, '127.0.0.1', 'regression');
        } finally {
            $property->setValue($service, $original);
        }
    }

    public function test_user_password_snapshot_cannot_receive_a_new_token_version(): void
    {
        $user = $this->actingAsUser();
        $service = Container::get(UserAuthService::class);
        $property = new \ReflectionProperty(UserAuthService::class, 'userRepository');
        $original = $property->getValue($service);
        $property->setValue($service, new class () extends UserRepository {
            public function findByAccount(string $account): ?array
            {
                $snapshot = parent::findByAccount($account);
                if ($snapshot !== null) {
                    Db::connection()->transaction(static function () use ($snapshot): void {
                        Db::table('users')->where('id', $snapshot['id'])->update(['password' => password_hash('Replacement#2026', PASSWORD_DEFAULT)]);
                        TokenVersion::bump((int) $snapshot['id'], 'user');
                    });
                }

                return $snapshot;
            }
        });
        try {
            $this->expectException(AuthException::class);
            $service->loginByPassword($user->mobile, $user->password, '127.0.0.1');
        } finally {
            $property->setValue($service, $original);
        }
    }

    public function test_old_cache_generation_cannot_restore_revoked_permissions(): void
    {
        $admin = $this->actingAsAdmin(['system.admin.list']);
        $permission = Container::get(PermissionChecker::class);
        $method = new \ReflectionMethod(PermissionChecker::class, 'remember');
        $method->invoke($permission, 'perm.list.' . $admin->id, $admin->id, static function () use ($permission, $admin): array {
            $permission->clearUserCache($admin->id);

            return ['stale.permission'];
        });
        $this->assertNotContains('stale.permission', $permission->getUserPermissions($admin->id));
    }
}
