# 中间件

中间件用于在请求到达 Controller 之前或响应返回之后执行通用逻辑。框架提供了认证、权限、日志、限流等内置中间件。

## 中间件架构

```
请求 → 全局中间件 → 应用中间件 → 路由中间件 → Controller
                                                    ↓
响应 ← 全局中间件 ← 应用中间件 ← 路由中间件 ← Controller
```

## 全局中间件

定义在 `app/middleware.php`，所有请求都会经过：

```php
return [
    // 跨域请求支持
    \app\adminapi\middleware\CorsMiddleware::class,
    // 多语言加载
    \think\middleware\LoadLangPack::class,
];
```

### CorsMiddleware

自动处理跨域请求，包括 OPTIONS 预检：

```php
class CorsMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $origin = $request->header('Origin', '*');

        // OPTIONS 预检直接返回 204
        if ($request->method(true) === 'OPTIONS') {
            return response('', 204)->header([
                'Access-Control-Allow-Origin'  => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, ...',
                'Access-Control-Max-Age'       => '86400',
            ]);
        }

        $response = $next($request);
        // 为正常响应添加 CORS 头
        $response->header([...]);
        return $response;
    }
}
```

## 中间件别名配置

在 `config/middleware.php` 中定义别名，路由中通过别名引用：

```php
return [
    'alias' => [
        'admin_auth'       => app\adminapi\middleware\AdminAuthMiddleware::class,
        'admin_permission' => app\adminapi\middleware\AdminPermissionMiddleware::class,
        'admin_log'        => app\adminapi\middleware\AdminLogMiddleware::class,
        'api_auth'         => app\api\middleware\ApiAuthMiddleware::class,
        'api_rate_limit'   => app\api\middleware\ApiRateLimitMiddleware::class,
        'api_sms_rate_limit' => app\api\middleware\SmsRateLimitMiddleware::class,
    ],
];
```

## Admin 中间件

### AdminAuthMiddleware（认证）

验证 JWT Token，将用户信息注入请求对象：

```php
class AdminAuthMiddleware extends Middleware
{
    protected TokenManager $tokenManager;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokenManager->getTokenFromHeader();

        if (!$token) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        $payload = $this->tokenManager->verify($token);

        // 校验 Token 类型
        if (($payload['type'] ?? '') !== 'admin') {
            return $this->errorResponse(lang('auth.token_invalid'), 401);
        }

        // 注入用户信息到请求
        $request->userId   = (int) ($payload['admin_id'] ?? 0);
        $request->username = (string) ($payload['username'] ?? '');

        return $next($request);
    }
}
```

### AdminPermissionMiddleware（权限）

通过反射读取 Controller 方法上的 `#[Permission]` 注解进行权限校验：

```php
class AdminPermissionMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $permissionName = $this->resolvePermission($request);

        // 无注解或 #[PermissionSkip] → 放行
        if (!$permissionName) {
            return $next($request);
        }

        // 校验权限
        if (!$this->permission->check($request->userId, $permissionName)) {
            return $this->errorResponse(lang('auth.permission_denied'), 403);
        }

        return $next($request);
    }
}
```

权限解析优先级：
1. `#[PermissionSkip]` → 跳过权限检查
2. `#[Permission('xxx')]` → 校验指定权限
3. 无注解 → 不做权限检查（放行）

### AdminLogMiddleware（操作日志）

自动记录所有 POST/PUT/DELETE 请求到 `admin_operation_logs` 表：

```php
class AdminLogMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $executionTime = round(microtime(true) - $startTime, 3);

        // 只记录写操作
        $method = strtoupper($request->method());
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $this->recordOperationLog($request, $response, $executionTime);
        }

        return $response;
    }
}
```

记录内容包括：管理员ID、请求方法、路径、IP、参数、响应结果、执行时间。

### 路由中使用

```php
// 需要认证 + 权限 + 日志的路由组
Route::group('system', function () {
    Route::get('admin', 'v1.system.AdminController/index');
    Route::post('admin', 'v1.system.AdminController/store');
    // ...
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);

// 无需认证的路由（如登录），使用登录限流
Route::group('auth', function () {
    Route::post('login', 'v1.auth.AuthController/login');
})->middleware([\app\adminapi\middleware\LoginRateLimitMiddleware::class]);
```

## C 端 API 中间件

### ApiAuthMiddleware

与 Admin 认证类似，但校验 `type: 'user'` 类型的 Token：

```php
class ApiAuthMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $this->tokenManager->verify($token);

        if (($payload['type'] ?? '') !== 'user') {
            return $this->errorResponse(lang('auth.token_invalid'), 401);
        }

        $request->userId = (int) ($payload['user_id'] ?? 0);
        return $next($request);
    }
}
```

### ApiRateLimitMiddleware

针对不同接口类型设置不同限流策略：

```php
class ApiRateLimitMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 登录/注册接口：同一 IP 每分钟 10 次
        if ($this->isAuthPath($path)) {
            $key = 'api_rate:auth:' . $request->ip();
            if (!$this->checkRateLimit($key, 10, 60)) {
                return $this->errorResponse(lang('messages.too_many_requests'), 429);
            }
        }

        // 通用接口：每分钟 60 次
        // 已认证用户按 userId 限流，未认证按 IP 限流
        // ...

        return $next($request);
    }
}
```

## 自定义中间件

### 基类 Middleware

框架提供 `core\http\Middleware` 基类，包含常用辅助方法：

```php
use core\http\Middleware;

class MyCustomMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 使用基类方法
        $token = $this->checkHeader($request, 'X-Custom-Token');
        $inWhitelist = $this->checkIpWhitelist($request, ['127.0.0.1']);
        $withinLimit = $this->checkRateLimit('my_key', 100, 60);

        // 返回错误响应
        if (!$token) {
            return $this->errorResponse('缺少认证信息', 401);
        }

        return $next($request);
    }
}
```

### 注册自定义中间件

1. 创建中间件类文件
2. 在 `config/middleware.php` 中添加别名
3. 在路由中使用别名引用

```php
// config/middleware.php
'alias' => [
    'my_custom' => app\adminapi\middleware\MyCustomMiddleware::class,
],

// 路由文件中使用
Route::group('custom', function () { ... })->middleware(['my_custom']);
```

## 中间件执行顺序

Admin API 请求完整执行链：

```
CorsMiddleware → LoadLangPack → AdminAuthMiddleware
    → AdminPermissionMiddleware → AdminLogMiddleware → Controller
```

路由组的中间件按数组顺序执行，确保 `admin_auth` 在 `admin_permission` 之前。
