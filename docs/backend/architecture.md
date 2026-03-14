# 架构设计

## 分层架构总览

Dev007 后端采用严格的分层架构，确保代码职责清晰、易于维护和测试：

```
请求 → Controller → Service → Repository → Model
                       │
                       ├── Listener（事件监听器，处理副作用）
                       └── Job（异步队列任务）
```

## 各层职责

| 层 | 目录 | 基类 | 职责 |
|---|---|---|---|
| Controller | `app/adminapi/controller/v1/` | `core\base\Controller` | 接收请求、参数校验、调用 Service、返回响应 |
| Service | `app/service/` | `core\base\Service` | 业务逻辑编排、事务管理、触发事件 |
| Repository | `app/repository/` | `core\base\Repository` | 数据访问封装、所有 ORM 查询集中于此 |
| Model | `app/model/` | `core\base\Model` | ORM 映射、关联关系、访问器/修改器 |
| Listener | `app/listener/` | — | 事件监听器，处理副作用（日志、通知、缓存清理） |
| Validate | `app/adminapi/validate/v1/` | — | 表单验证规则 |

## 数据流向规则

### 严格遵守

- Controller **只调用 Service**，不直接操作 Repository 或 Model
- Service **只调用 Repository**，不直接使用 `Db::table()` 或 Model 静态方法（如 `::where()`、`::find()`、`::create()`）
- Repository 封装所有 ORM 查询，是唯一与 Model 交互的层
- 事务管理 `Db::startTrans() / commit() / rollback()` 放在 Service 层
- `Db::query()` 仅允许在 `CodeGeneratorService` 中使用（元编程/Schema 内省）

### 示例流程

以管理员登录为例：

```
AdminController::login()
  → AdminService::login($username, $password)
      → AdminRepository::findByUsername($username)  // 查询用户
      → TokenManager::createToken($admin)            // 生成 Token
      → $this->trigger('admin.login.success', $admin) // 触发事件
  → return $this->success('登录成功', $data)
```

## 自动依赖注入（DI）

Controller 和 Service 的基类都内置了自动 DI 机制。子类只需声明带类型的 `protected` 属性，基类会自动从容器中注入实例：

```php
class AdminService extends Service
{
    protected AdminRepository $adminRepository;   // 自动注入
    protected TokenManager $tokenManager;          // 自动注入

    public function login(string $username, string $password): array
    {
        // 直接使用，无需手动实例化
        $admin = $this->adminRepository->findByUsername($username);
        $token = $this->tokenManager->createToken($admin);
        // ...
    }
}
```

同样适用于 Controller：

```php
class AdminController extends Controller
{
    protected AdminService $adminService;  // 自动注入

    public function login(): Response
    {
        $data = $this->adminService->login(
            $this->request->post('username'),
            $this->request->post('password')
        );
        return $this->success('登录成功', $data);
    }
}
```

## 事件系统

### 概述

事件系统用于将副作用逻辑从主业务流程中解耦。Service 通过 `$this->trigger()` 方法触发事件，Listener 负责处理具体的副作用逻辑。

### 判断标准

- 如果该操作失败**不影响主流程** → 放 Listener
- 如果该操作**必须成功** → 留在 Service

### 事件配置

事件与监听器的映射关系配置在 `app/event.php` 中：

```php
'listen' => [
    // 管理员事件
    'admin.login.success' => [AdminLoginSuccessListener::class],
    'admin.login.failed'  => [AdminLoginFailedListener::class],

    // 系统配置事件
    'config.changed'      => [ConfigChangedListener::class],

    // 用户事件
    'user.register'       => [UserRegisterListener::class],
    'user.login'          => [UserLoginListener::class],

    // 支付事件
    'payment.success'     => [PaymentSuccessListener::class],

    // 反馈事件
    'feedback.created'    => [FeedbackCreatedListener::class],
],
```

### 添加新事件

添加新的副作用只需两步：

1. 创建 Listener 类
2. 在 `event.php` 中注册映射

无需修改 Service 代码。

### 在 Service 中触发事件

```php
class OrderService extends Service
{
    public function pay(int $orderId): void
    {
        // ... 支付业务逻辑 ...

        // 触发事件，由 Listener 处理后续副作用
        $this->trigger('payment.success', $order);
    }
}
```

## 中间件系统

### 全局中间件

配置在 `app/middleware.php`，对所有请求生效：

- `CorsMiddleware` — 跨域请求支持
- `LoadLangPack` — 多语言加载

### 管理后台中间件

位于 `app/adminapi/middleware/`，按需应用于管理后台路由：

| 中间件 | 职责 |
|---|---|
| `AdminAuthMiddleware` | JWT Token 认证，验证登录状态 |
| `AdminPermissionMiddleware` | RBAC 权限校验，检查接口访问权限 |
| `AdminLogMiddleware` | 操作日志，自动记录 POST/PUT/DELETE 请求 |
| `LoginRateLimitMiddleware` | 登录频率限制，防止暴力破解 |
| `RateLimitMiddleware` | 通用接口频率限制 |

## 异常处理

系统使用 `app/ExceptionHandle.php` 统一处理异常，对 API 请求返回 JSON 格式的错误响应。

### 内置异常类型

| 异常类 | 用途 |
|---|---|
| `ApiException` | 通用 API 异常 |
| `AuthException` | 认证失败（未登录、Token 过期） |
| `PermissionException` | 权限不足 |
| `BusinessException` | 业务逻辑异常 |
| `ValidationException` | 数据验证异常 |

### API 响应格式

```php
// 成功响应
return $this->success('操作成功', $data);

// 错误响应
return $this->error('错误信息');
```

## 操作日志

系统提供多层级的日志记录机制：

- **HTTP 级别** — `AdminLogMiddleware` 自动记录所有 POST/PUT/DELETE 请求到 `admin_operation_logs` 表
- **业务级别** — Service 中的 `$this->log()` 写入文件日志
- **登录日志** — 通过 `admin.login.success/failed` 事件，由 Listener 写入 `admin_login_logs` 表

## 代码生成器

通过命令行或管理后台「代码生成」页面使用：

```bash
php think make:crud
```

自动生成以下文件：

- **后端**：Model、Repository、Service、Controller、Validate、Route
- **前端**：API 文件（TypeScript）、列表页和表单组件（Vue）

生成的代码严格遵循 `Controller → Service → Repository → Model` 分层架构。
