# API 规范

本文档定义后端 API 的响应格式、错误码、路由约定、分页参数和认证方式。

## 响应格式

所有 API 响应使用统一的 JSON 结构：

### 成功响应

```json
{
    "code": 200,
    "message": "操作成功",
    "data": { ... },
    "timestamp": 1705285800
}
```

### 错误响应

```json
{
    "code": 400,
    "message": "参数验证失败",
    "data": {},
    "timestamp": 1705285800
}
```

### 分页响应

```json
{
    "code": 200,
    "message": "获取成功",
    "data": {
        "list": [ ... ],
        "pagination": {
            "current_page": 1,
            "per_page": 15,
            "total": 100,
            "last_page": 7
        }
    },
    "timestamp": 1705285800
}
```

### Controller 中使用

```php
// 成功响应
return $this->success('创建成功', $result);

// 错误响应
return $this->error('参数错误', 400);

// 分页响应 — 直接传入 Repository->getList() 返回值
$result = $this->adminService->getAdminList($params);
return $this->paginate($result);
```

`core\http\Response` 类提供的静态方法：

| 方法 | 用途 |
|------|------|
| `Response::success($message, $data, $code)` | 成功响应 |
| `Response::error($message, $code, $data)` | 错误响应 |
| `Response::paginate($data, $message)` | 分页响应 |
| `Response::list($list, $message)` | 列表响应（不分页） |
| `Response::noContent()` | 204 无内容响应 |
| `Response::download($file, $name)` | 文件下载响应 |

## 错误码分类

### HTTP 状态码

| 状态码 | 含义 | 场景 |
|--------|------|------|
| 200 | 成功 | 正常操作 |
| 201 | 创建成功 | 资源创建 |
| 204 | 无内容 | 删除成功 |
| 400 | 请求错误 | 参数验证失败、业务逻辑错误 |
| 401 | 未认证 | Token 缺失或过期 |
| 403 | 无权限 | 权限不足 |
| 429 | 请求过多 | 触发限流 |
| 500 | 服务器错误 | 系统内部异常 |

### 业务错误码（code 字段）

```
1xxx — 认证相关
  1001  未登录 / Token 缺失
  1002  Token 过期
  1003  Token 无效
  1004  账户已被禁用

2xxx — 参数验证
  2001  必填字段缺失
  2002  字段格式错误
  2003  字段值超出范围

3xxx — 业务逻辑
  3001  记录不存在
  3002  数据重复（如用户名已存在）
  3003  状态不允许操作
  3004  关联数据存在，无法删除

4xxx — 支付相关
  4001  余额不足
  4002  支付超时
  4003  支付渠道异常

5xxx — 系统错误
  5001  数据库异常
  5002  缓存服务异常
  5003  第三方接口异常
```

### 异常类对应

```php
// 业务异常 → code: 400
throw new BusinessException('用户名已存在');

// 验证异常 → code: 400
throw new ValidationException('密码长度不足');

// 认证异常 → code: 401
throw new AuthException('Token 已过期');

// 权限异常 → code: 403
throw new PermissionException('无权限访问');
```

## API 路由约定

### 路由前缀

| 应用 | 前缀 | 说明 |
|------|------|------|
| Admin API | `/adminapi/` | 管理后台接口 |
| C 端 API | `/api/` | 用户端接口 |

### RESTful 路由

```php
// 标准 CRUD 路由
Route::group('system/admin', function () {
    Route::get('', 'v1.system.AdminController/index');           // 列表
    Route::get(':id', 'v1.system.AdminController/show');         // 详情
    Route::post('', 'v1.system.AdminController/store');          // 创建
    Route::put(':id', 'v1.system.AdminController/update');       // 更新
    Route::delete(':id', 'v1.system.AdminController/delete');    // 删除
});

// 扩展操作
Route::put(':id/status', 'v1.system.AdminController/status');            // 状态切换
Route::put(':id/reset-password', 'v1.system.AdminController/resetPassword'); // 重置密码
Route::post('batch-delete', 'v1.system.AdminController/batchDelete');    // 批量删除
```

### 路由文件组织

```
app/adminapi/route/
├── auth.php       — 认证路由（登录、登出）
├── system.php     — 系统管理路由（管理员、角色、菜单、配置）
├── upload.php     — 上传路由
└── ...
```

路由组使用中间件：

```php
// 需要认证的路由
Route::group('system', function () { ... })
    ->middleware(['admin_auth', 'admin_permission', 'admin_log']);

// 无需认证的路由
Route::group('auth', function () {
    Route::post('login', 'v1.auth.AuthController/login');
})->middleware([LoginRateLimitMiddleware::class]);
```

## 分页参数

### 请求参数

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `page` | int | 1 | 页码 |
| `limit` | int | 15 | 每页条数 |
| `keyword` | string | - | 关键词搜索 |
| `status` | int | - | 状态筛选 |
| `order` | string | `id desc` | 排序字段和方向 |

### 请求示例

```
GET /adminapi/system/admin?page=1&limit=20&keyword=admin&status=1
```

### 响应结构

```json
{
    "code": 200,
    "message": "获取成功",
    "data": {
        "list": [
            { "id": 1, "username": "admin", "status": 1, ... },
            { "id": 2, "username": "editor", "status": 1, ... }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 50,
            "last_page": 3
        }
    },
    "timestamp": 1705285800
}
```

## 认证方式

### JWT Bearer Token

所有需要认证的接口在请求头中携带 Token：

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Token 获取

```
POST /adminapi/auth/login
Content-Type: application/json

{
    "username": "admin",
    "password": "123456"
}
```

响应：

```json
{
    "code": 200,
    "message": "登录成功",
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIs...",
        "admin": { "id": 1, "username": "admin", ... }
    }
}
```

### Token 类型

| 类型 | Payload 字段 | 用途 |
|------|-------------|------|
| `admin` | `admin_id`, `username`, `type` | 管理后台认证 |
| `user` | `user_id`, `type` | C 端用户认证 |

### Token 刷新与注销

```
POST /adminapi/auth/refresh    — 刷新 Token
POST /adminapi/auth/logout     — 注销登录
GET  /adminapi/auth/info       — 获取当前用户信息
```

## 多语言支持

API 响应消息支持多语言，通过请求头指定语言：

```
think-lang: zh-cn
```

Controller 中使用 `lang()` 函数：

```php
return $this->success(lang('messages.create_success'), $result);
return $this->error(lang('auth.login_failed'));
```

语言包文件位于 `app/lang/` 目录下。
