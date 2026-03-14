# Controller 开发

Controller 是请求入口层，负责接收请求、参数校验、调用 Service、返回响应。所有 Controller 继承 `core\base\Controller` 基类。

## 基类功能

### 自动依赖注入

基类通过反射自动解析子类声明的 `protected` 类型属性，从容器中注入实例：

```php
namespace app\adminapi\controller\v1\system;

use app\service\system\AdminService;
use app\service\system\RoleService;
use core\base\Controller;

class AdminController extends Controller
{
    protected AdminService $adminService;   // 自动注入
    protected RoleService $roleService;     // 自动注入
}
```

无需手动 `new` 或在构造函数中注入，声明属性类型即可。

### 响应方法

基类提供三个标准响应方法：

```php
// 成功响应 — 返回 { code: 200, message, data, timestamp }
return $this->success('操作成功', $data);
return $this->success('创建成功', $result, 201);

// 错误响应 — 返回 { code: 400, message, data, timestamp }
return $this->error('参数错误');
return $this->error('权限不足', 403);

// 分页响应 — 直接传入 Repository 的 getList 返回值
return $this->paginate($result);
```

### 参数获取

```php
// 获取所有请求参数
$params = $this->getRequestData();

// 获取当前登录用户ID（中间件注入）
$userId = $this->getUserId();

// 获取当前登录用户信息
$userInfo = $this->getUserInfo();

// 获取路由参数
$id = (int) $this->request->param('id');

// 获取 POST 数据
$data = $this->request->post();
$status = (int) $this->request->post('status');
```

### 参数验证

支持两种验证方式：

```php
// 方式一：使用验证器类 + 场景
$data = $this->request->post();
$this->validate($data, AdminValidate::class, [], 'create');

// 方式二：行内规则
$this->validate(['password' => $password], [
    'password' => 'require|length:6,20'
]);
```

验证失败自动抛出 `ValidationException`，由全局异常处理返回 400 错误。

## 权限注解

使用 PHP 8 Attribute 声明接口所需权限，`AdminPermissionMiddleware` 自动通过反射读取并校验：

```php
use core\attribute\Permission;
use core\attribute\PermissionSkip;

class AdminController extends Controller
{
    #[Permission('system.admin.list')]    // 需要 system.admin.list 权限
    public function index(): Response { ... }

    #[Permission('system.admin.create')]  // 需要 system.admin.create 权限
    public function store(): Response { ... }

    #[PermissionSkip]                     // 跳过权限检查
    public function changePassword(): Response { ... }
}
```

权限命名规范：`{模块}.{资源}.{操作}`，如 `system.admin.list`、`system.role.create`。

## Admin API vs C 端 API

| 特性 | Admin API | C 端 API |
|------|----------|---------|
| 命名空间 | `app\adminapi\controller\v1\` | `app\api\controller\v1\` |
| 路由前缀 | `/adminapi/` | `/api/` |
| 认证中间件 | `admin_auth` | `api_auth` |
| 权限中间件 | `admin_permission`（注解驱动） | 无 |
| Token 类型 | `type: 'admin'` | `type: 'user'` |
| 操作日志 | `admin_log` 自动记录写操作 | 无 |

## 完整示例

```php
namespace app\adminapi\controller\v1\system;

use app\service\system\AdminService;
use app\adminapi\validate\v1\system\AdminValidate;
use core\base\Controller;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use think\Response;

class AdminController extends Controller
{
    protected AdminService $adminService;

    #[Permission('system.admin.list')]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->adminService->getAdminList($params);
        return $this->paginate($result);
    }

    #[Permission('system.admin.create')]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, AdminValidate::class, [], 'create');

        $data['created_by'] = $this->getUserId();
        $result = $this->adminService->createAdmin($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.admin.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->adminService->deleteAdmin($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[PermissionSkip]
    public function changePassword(): Response
    {
        $data = $this->request->post();
        $this->validate($data, [
            'old_password' => 'require',
            'new_password' => 'require|length:6,20|different:old_password'
        ]);

        $this->adminService->changePassword(
            $this->getUserId(),
            $data['old_password'],
            $data['new_password']
        );
        return $this->success(lang('messages.password_change_success'));
    }
}
```

## 注意事项

- Controller **只调用 Service**，不直接操作 Repository 或 Model
- 所有返回值使用 `$this->success()` / `$this->error()` / `$this->paginate()` 统一格式
- 使用 `lang()` 函数实现多语言支持
- 路由定义在 `app/adminapi/route/` 目录下的 PHP 文件中
