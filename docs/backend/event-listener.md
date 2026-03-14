# 事件与监听器

事件系统用于将副作用逻辑从 Service 中解耦。Service 通过 `$this->trigger()` 触发事件，Listener 处理日志记录、缓存清理、通知发送等副作用操作。

## 事件系统概述

```
Service 业务方法
    ↓ $this->trigger('event.name', $data)
事件调度器（ThinkPHP Event）
    ↓
Listener::handle($event)  ← 处理副作用
```

核心原则：**如果该操作失败不影响主流程，就放到 Listener 中。**

## event.php 配置

所有事件与监听器的映射关系定义在 `app/event.php`：

```php
return [
    'bind' => [],

    'listen' => [
        // ---- 管理员事件 ----
        'admin.login.success' => [\app\listener\system\AdminLoginSuccessListener::class],
        'admin.login.failed'  => [\app\listener\system\AdminLoginFailedListener::class],

        // ---- 系统配置事件 ----
        'config.changed' => [\app\listener\system\ConfigChangedListener::class],

        // ---- 用户事件 ----
        'user.register' => [\app\listener\user\UserRegisterListener::class],
        'user.login'    => [\app\listener\user\UserLoginListener::class],

        // ---- 支付事件 ----
        'payment.success' => [\app\listener\payment\PaymentSuccessListener::class],

        // ---- 反馈事件 ----
        'feedback.created' => [\app\listener\feedback\FeedbackCreatedListener::class],

        // ---- 消息推送事件 ----
        'message.created' => [\app\listener\MessagePushListener::class],
    ],

    'subscribe' => [],
];
```

添加新的副作用只需在 `listen` 数组中追加 Listener 类，无需修改 Service 代码。

## 事件命名规范

事件名称采用 `{模块}.{资源}.{动作}` 格式：

```
admin.login.success    — 管理员登录成功
admin.login.failed     — 管理员登录失败
config.changed         — 系统配置变更
user.register          — 用户注册
user.login             — 用户登录
payment.success        — 支付成功
feedback.created       — 反馈创建
message.created        — 消息创建
```

## 创建 Listener

### 目录结构

```
app/listener/
├── system/
│   ├── AdminLoginSuccessListener.php
│   ├── AdminLoginFailedListener.php
│   └── ConfigChangedListener.php
├── user/
│   ├── UserRegisterListener.php
│   └── UserLoginListener.php
├── payment/
│   └── PaymentSuccessListener.php
└── feedback/
    └── FeedbackCreatedListener.php
```

### Listener 结构

每个 Listener 只需实现 `handle()` 方法：

```php
namespace app\listener\system;

use app\model\system\AdminLoginLog;
use app\repository\system\AdminRepository;

/**
 * 管理员登录成功监听器
 *
 * 事件数据：
 * - admin_id: int       管理员ID
 * - username: string    用户名
 * - ip: string          登录IP
 * - user_agent: string  浏览器UA
 */
class AdminLoginSuccessListener
{
    public function handle(array $event): void
    {
        // 更新最后登录信息
        app()->make(AdminRepository::class)
            ->updateLastLogin((int) $event['admin_id'], $event['ip']);

        // 记录登录成功日志
        AdminLoginLog::record([
            'admin_id'     => $event['admin_id'],
            'username'     => $event['username'],
            'ip'           => $event['ip'],
            'user_agent'   => $event['user_agent'],
            'login_result' => true,
            'login_message' => lang('messages.login_success'),
        ]);
    }
}
```

### 配置变更监听器示例

```php
namespace app\listener\system;

use think\facade\Cache;
use think\facade\Log;

/**
 * 系统配置变更监听器
 *
 * 事件数据：
 * - keys: array   变更的配置key列表
 * - group: string 变更的配置分组
 */
class ConfigChangedListener
{
    public function handle(array $event): void
    {
        // 清除配置缓存
        Cache::delete('system_configs');
        Cache::tag('config')->clear();

        Log::info('系统配置已更新，缓存已清除', [
            'keys'  => $event['keys'] ?? [],
            'group' => $event['group'] ?? '',
        ]);
    }
}
```

## Service 中触发事件

在 Service 中使用基类的 `$this->trigger()` 方法触发事件：

```php
class AdminService extends Service
{
    public function login(string $username, string $password, string $ip, string $userAgent): array
    {
        $loginContext = [
            'username'   => $username,
            'ip'         => $ip,
            'user_agent' => $userAgent,
        ];

        $admin = $this->adminRepository->findByUsername($username);

        if (!$admin) {
            // 登录失败事件
            $this->trigger('admin.login.failed', $loginContext + ['message' => '用户名不存在']);
            throw new BusinessException(lang('auth.login_failed'));
        }

        // ... 验证逻辑 ...

        // 登录成功事件
        $this->trigger('admin.login.success', $loginContext + ['admin_id' => $admin['id']]);

        return ['token' => $token, 'admin' => $adminInfo];
    }
}
```

## 添加新事件的步骤

1. **创建 Listener 文件**：在 `app/listener/` 对应模块目录下创建

2. **注册到 event.php**：在 `listen` 数组中添加映射

   ```php
   'order.created' => [\app\listener\order\OrderCreatedListener::class],
   ```

3. **在 Service 中触发**：

   ```php
   $this->trigger('order.created', ['order_id' => $order['id'], 'user_id' => $userId]);
   ```

一个事件可以绑定多个 Listener，按数组顺序依次执行：

```php
'order.created' => [
    \app\listener\order\OrderNotifyListener::class,    // 发送通知
    \app\listener\order\OrderStatisticsListener::class, // 更新统计
],
```

## 注意事项

- Listener 中的异常不会影响 Service 主流程
- 事件数据通过数组传递，建议在 Listener 类文档注释中说明数据结构
- Listener 中可以通过 `app()->make()` 获取 Repository 等依赖
- 耗时操作（如发送邮件）建议在 Listener 中分发到队列 Job 异步处理
