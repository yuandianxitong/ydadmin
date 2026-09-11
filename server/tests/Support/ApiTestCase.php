<?php

declare(strict_types=1);

namespace tests\Support;

use app\repository\system\SystemConfigRepository;
use core\auth\Permission;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\datascope\DataScopeResolver;
use support\Container;
use support\Db;
use support\Log;
use support\Redis;
use support\Request;
use tests\TestCase;
use Webman\App;

/**
 * 接口测试基类：请求经 Webman\App::onMessage() 完整执行，覆盖路由、中间件顺序、
 * 异常处理、fallback 与每请求的 Context 销毁。
 */
abstract class ApiTestCase extends TestCase
{
    /** 进程内共享的 App 实例（测试代码，不受 check:context 约束） */
    private static ?App $app = null;

    /** @var array<string, list<int>> 本用例创建的行：表 → id，tearDown 时删除 */
    private array $created = [];

    /** @var list<int> 本用例创建的管理员（tearDown 时连同关联行、缓存一起清） */
    private array $createdAdminIds = [];

    /** @var array<string, string> 被本用例改过的配置的原值 */
    private array $originalConfigs = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::ensureRoutesLoaded();
        self::$app ??= new App(Request::class, Log::channel('default'), app_path(), public_path());
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        parent::tearDown();
    }

    /**
     * 当场创建一个管理员并签发 token（spec §7.1）。不用事务回滚隔离——afterCommit 回调不会触发；
     * 夹具登记自己创建的行，tearDown 时删除。
     *
     * @param list<string>|string   $permissions 权限点列表（在种子菜单里按 menus.permission 找菜单授权）；
     *                                           'super' 表示挂种子里的超管角色（id=1）
     * @param array<string, mixed>  $admin       覆盖 admins 列，如 ['department_id' => 2, 'status' => 0]
     * @param array<string, mixed>  $role        覆盖为它新建的专属角色的列，如 ['data_scope' => 5]；
     *                                           特殊键 dept_ids 写入 role_departments
     */
    protected function actingAsAdmin(array|string $permissions = [], array $admin = [], array $role = []): TestAdmin
    {
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $username = (string) ($admin['username'] ?? "t_{$suffix}");
        $password = 'Passw0rd!';
        $adminId = (int) Db::table('admins')->insertGetId(array_merge([
            'username'   => $username,
            'email'      => "{$username}@test.local",
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'nickname'   => $username,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $admin));
        $this->trackAdmin($adminId);

        if ($permissions === 'super') {
            $roleId = 1;
        } else {
            $permissions = (array) $permissions;
            $deptIds = (array) ($role['dept_ids'] ?? []);
            unset($role['dept_ids']);
            $roleId = (int) Db::table('roles')->insertGetId(array_merge([
                'name'       => "r_{$suffix}",
                'title'      => "测试角色{$suffix}",
                'data_scope' => 1,
                'is_system'  => 0,
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $role));
            $this->track('roles', $roleId);

            $menuIds = $permissions === [] ? [] : Db::table('menus')->whereIn('permission', $permissions)->pluck('id')->all();
            $this->assertCount(count(array_unique($permissions)), $menuIds, '种子菜单里找不到部分权限点：' . implode(',', $permissions));
            foreach ($menuIds as $menuId) {
                Db::table('role_menus')->insert(['role_id' => $roleId, 'menu_id' => $menuId, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ($deptIds as $deptId) {
                Db::table('role_departments')->insert(['role_id' => $roleId, 'department_id' => (int) $deptId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        Db::table('admin_roles')->insert(['admin_id' => $adminId, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now]);

        // 测试库重建后自增 id 会复用：先清掉这个 id 可能残留的缓存
        $this->forgetAdminCaches($adminId);

        $token = TokenManager::scope('admin')->generate([
            'admin_id' => $adminId,
            'username' => $username,
            'ver'      => TokenVersion::current($adminId),
        ]);

        return new TestAdmin($adminId, $username, $password, $token);
    }

    /** @param array<string, mixed> $attributes */
    protected function createDepartment(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('departments')->insertGetId(array_merge([
            'parent_id'  => 0,
            'name'       => '测试部门' . bin2hex(random_bytes(3)),
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->track('departments', $id);

        return $id;
    }

    /** 登记需要在 tearDown 删除的行（经接口创建的记录也要登记）。 */
    protected function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    /** 登记管理员：除删行外，还会清它的角色关联、登录日志、操作日志、权限缓存与 token 版本号。 */
    protected function trackAdmin(int $id): void
    {
        $this->track('admins', $id);
        $this->createdAdminIds[] = $id;
    }

    /** 清掉某个管理员在 Redis 里的派生状态。 */
    protected function forgetAdminCaches(int $adminId): void
    {
        Container::get(Permission::class)->clearUserCache($adminId);
        Redis::del("admin_token_ver:{$adminId}");
        Container::get(DataScopeResolver::class)->forget($adminId);
    }

    /** @return array{0: string, 1: string} [captcha_key, 验证码明文]（从服务同一个 Redis key 读取，校验照常执行） */
    protected function solveCaptcha(): array
    {
        $key = (string) $this->get('/adminapi/auth/captcha')->assertOk()->data()['key'];

        return [$key, (string) Redis::get('captcha.' . $key)];
    }

    protected function login(string $username, string $password): TestResponse
    {
        [$key, $code] = $this->solveCaptcha();

        return $this->post('/adminapi/auth/login', ['username' => $username, 'password' => $password, 'captcha_key' => $key, 'captcha' => $code]);
    }

    /** 临时修改一项系统配置；tearDown 时恢复原值并清配置缓存。 */
    protected function setConfig(string $key, string $value): void
    {
        $this->rememberConfig($key);
        Db::table('system_configs')->where('config_key', $key)->update(['config_value' => $value]);
        (new SystemConfigRepository())->forgetCache();
    }

    /** 只登记配置原值（经接口修改配置的用例先调它）；tearDown 时恢复原值并清配置缓存。 */
    protected function rememberConfig(string ...$keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->originalConfigs)) {
                continue;
            }
            $original = Db::table('system_configs')->where('config_key', $key)->value('config_value');
            $this->assertNotNull($original, "system_configs 里没有 {$key}");
            $this->originalConfigs[$key] = (string) $original;
        }
    }

    private function cleanupFixtures(): void
    {
        foreach ($this->originalConfigs as $key => $value) {
            Db::table('system_configs')->where('config_key', $key)->update(['config_value' => $value]);
        }
        if ($this->originalConfigs !== []) {
            (new SystemConfigRepository())->forgetCache();
        }
        $this->originalConfigs = [];

        $adminIds = $this->createdAdminIds;
        $roleIds = $this->created['roles'] ?? [];
        $menuIds = $this->created['menus'] ?? [];
        if ($adminIds !== []) {
            Db::table('admin_roles')->whereIn('admin_id', $adminIds)->delete();
            Db::table('admin_login_logs')->whereIn('admin_id', $adminIds)->delete();
            Db::table('admin_operation_logs')->whereIn('admin_id', $adminIds)->delete();
        }
        if ($roleIds !== []) {
            Db::table('admin_roles')->whereIn('role_id', $roleIds)->delete();
            Db::table('role_menus')->whereIn('role_id', $roleIds)->delete();
            Db::table('role_departments')->whereIn('role_id', $roleIds)->delete();
        }
        if ($menuIds !== []) {
            Db::table('role_menus')->whereIn('menu_id', $menuIds)->delete();
        }
        foreach (array_reverse($this->created, true) as $table => $ids) {
            Db::table($table)->whereIn('id', array_values(array_unique($ids)))->delete();
        }
        foreach ($adminIds as $id) {
            $this->forgetAdminCaches($id);
        }
        // 夹具建的角色、菜单会影响别人的权限集合
        Container::get(Permission::class)->clearAllCache();
        // 夹具建的部门、角色会改变别人的数据范围
        Container::get(DataScopeResolver::class)->forgetAll();
        $this->created = [];
        $this->createdAdminIds = [];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function call(string $method, string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $method = strtoupper($method);
        $body = '';
        if ($method === 'GET' || $method === 'DELETE') {
            if ($data !== []) {
                $uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query($data);
            }
        } else {
            $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
        }

        $headers += ['Host' => 'localhost', 'Accept' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $headers['Content-Length'] = (string) strlen($body);

        $raw = "{$method} {$uri} HTTP/1.1\r\n";
        foreach ($headers as $name => $value) {
            $raw .= "{$name}: {$value}\r\n";
        }
        $raw .= "\r\n" . $body;

        $connection = new FakeConnection();
        $request = new Request($raw);
        $request->connection = $connection;

        /** @var App $app */
        $app = self::$app;
        $app->onMessage($connection, $request);

        return new TestResponse($connection->response);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    protected function get(string $uri, array $query = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, $query, $token, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function post(string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $token, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function put(string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $token, $headers);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    protected function delete(string $uri, array $query = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $query, $token, $headers);
    }
}
