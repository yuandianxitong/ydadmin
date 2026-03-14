# Service 开发

Service 是业务逻辑层，负责编排业务流程、事务管理和触发事件。所有 Service 继承 `core\base\Service` 基类。

## 基类功能

### 自动依赖注入

与 Controller 相同，基类通过反射自动注入子类声明的 `protected` 类型属性：

```php
namespace app\service\system;

use app\repository\system\AdminRepository;
use app\repository\system\RoleRepository;
use core\base\Service;
use core\auth\TokenManager;

class AdminService extends Service
{
    protected AdminRepository $adminRepository;   // 自动注入
    protected RoleRepository $roleRepository;     // 自动注入
    protected TokenManager $tokenManager;          // 自动注入
}
```

### 事件触发

通过 `$this->trigger()` 方法触发事件，将副作用委托给 Listener 处理：

```php
// 登录成功后触发事件
$this->trigger('admin.login.success', [
    'admin_id' => $admin['id'],
    'username' => $admin['username'],
    'ip'       => $ip,
    'user_agent' => $userAgent,
]);

// 登录失败后触发事件
$this->trigger('admin.login.failed', [
    'username' => $username,
    'ip'       => $ip,
    'message'  => '密码错误',
]);
```

### 日志记录

使用 `$this->log()` 方法写入文件日志（非数据库）：

```php
$this->log('管理员创建成功', ['admin_id' => $id], 'info');
$this->log('支付回调处理失败', ['order_no' => $orderNo], 'error');
```

### 业务异常

使用 `$this->throwBusinessException()` 抛出业务异常：

```php
$this->throwBusinessException('用户名已存在');
$this->throwBusinessException('余额不足', 400);

// 也可直接抛出
throw new BusinessException('账户已被禁用');
```

### 缓存操作

```php
// 读取缓存
$value = $this->cache('config_key');

// 写入缓存，默认 3600 秒
$this->cache('config_key', $value);
$this->cache('config_key', $value, 7200);

// 删除缓存
$this->forgetCache('config_key');

// 缓存标签
$this->cacheTag('config')->clear();
```

## 事务管理

数据库事务在 Service 层管理，使用 `Db::startTrans()` / `commit()` / `rollback()`：

```php
use think\facade\Db;

public function createAdmin(array $data): array
{
    Db::startTrans();
    try {
        // 创建管理员
        $admin = $this->adminRepository->create($data);

        // 分配角色
        if (!empty($data['role_ids'])) {
            $this->adminRepository->assignRoles($admin['id'], $data['role_ids']);
        }

        Db::commit();

        // 事务成功后触发事件
        $this->trigger('admin.created', $admin);

        return $admin;
    } catch (\Exception $e) {
        Db::rollback();
        throw $e;
    }
}
```

::: warning 事件触发时机
`$this->trigger()` 应放在 `Db::commit()` 之后，确保数据已持久化再通知 Listener。
:::

## 数据访问规则

Service 中**禁止**直接操作 Model 或 Db 查询：

```php
// 错误 — 直接使用 Model 静态方法
$admin = Admin::where('username', $username)->find();

// 错误 — 直接使用 Db
$result = Db::table('admins')->where('id', $id)->find();

// 正确 — 通过 Repository
$admin = $this->adminRepository->findByUsername($username);
```

唯一例外：`Db::startTrans()` / `commit()` / `rollback()` 的事务管理允许在 Service 中使用。

## 副作用判断标准

Service 方法中的逻辑应区分"核心流程"和"副作用"：

| 类型 | 放置位置 | 示例 |
|------|---------|------|
| 核心流程 | Service 方法内 | 创建记录、更新状态、生成 Token |
| 副作用 | Listener | 记录日志、发送通知、清除缓存、更新统计 |

判断标准：**如果该操作失败不影响主流程，就放到 Listener 中。**

## 完整示例

```php
namespace app\service\system;

use app\repository\system\AdminRepository;
use core\base\Service;
use core\auth\TokenManager;
use core\exception\BusinessException;
use think\facade\Db;

class AdminService extends Service
{
    protected AdminRepository $adminRepository;
    protected TokenManager $tokenManager;

    /**
     * 管理员登录
     */
    public function login(string $username, string $password, string $ip, string $userAgent): array
    {
        $loginContext = ['username' => $username, 'ip' => $ip, 'user_agent' => $userAgent];

        $admin = $this->adminRepository->findByUsername($username);
        if (!$admin) {
            $this->trigger('admin.login.failed', $loginContext + ['message' => '用户名不存在']);
            throw new BusinessException(lang('auth.login_failed'));
        }

        if ($admin['status'] != 1) {
            $this->trigger('admin.login.failed', $loginContext + ['admin_id' => $admin['id'], 'message' => '账户已被禁用']);
            throw new BusinessException(lang('auth.account_disabled'));
        }

        if (!password_verify($password, $admin['password'])) {
            $this->trigger('admin.login.failed', $loginContext + ['admin_id' => $admin['id'], 'message' => '密码错误']);
            throw new BusinessException(lang('auth.login_failed'));
        }

        $token = $this->tokenManager->generate([
            'admin_id' => $admin['id'],
            'username' => $admin['username'],
            'type'     => 'admin',
        ]);

        // 副作用交给 Listener：更新登录信息 + 记录日志
        $this->trigger('admin.login.success', $loginContext + ['admin_id' => $admin['id']]);

        return ['token' => $token, 'admin' => $this->getAdminInfo((int) $admin['id'])];
    }

    /**
     * 更新管理员状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $admin = $this->adminRepository->find($id);
        if (!$admin) {
            $this->throwBusinessException('管理员不存在');
        }

        return $this->adminRepository->update($id, ['status' => $status]);
    }
}
```

## 注意事项

- Service **只调用 Repository**，不直接使用 Model 静态方法或 `Db::table()`
- 事务控制（`Db::startTrans/commit/rollback`）放在 Service 层
- 副作用逻辑通过 `$this->trigger()` 委托给 Listener
- 初始化逻辑可重写 `initialize()` 方法
