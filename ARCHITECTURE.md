# 元点Admin 架构速查表

## 后端分层架构

```
请求 → Controller → Service → Repository → Model
                       ↓
                    Listener（事件驱动副作用）
```

| 层 | 目录 | 职责 | 禁止 |
|---|---|---|---|
| **Controller** | `app/adminapi/controller/v1/` | 接收请求、参数校验、调用 Service、返回响应 | 不直接操作 Repository 或 Model |
| **Service** | `app/service/` | 业务逻辑编排、事务管理、触发事件 | 不直接用 `Db::table()` 或 Model 静态方法 |
| **Repository** | `app/repository/` | 数据访问封装、所有 ORM 查询 | 不包含业务逻辑 |
| **Model** | `app/model/` | ORM 映射、关联关系、访问器/修改器 | 不包含查询逻辑 |
| **Listener** | `app/listener/` | 处理副作用（日志、通知、缓存清理） | 不影响主流程 |

## 依赖注入

Controller 和 Service 的基类内置自动 DI。声明带类型的 `protected` 属性即可：

```php
class ArticleService extends Service
{
    protected ArticleRepository $articleRepository;   // 自动注入
    protected CategoryRepository $categoryRepository; // 自动注入
}
```

## 事件系统

Service 中触发事件：
```php
$this->trigger('admin.login.success', ['admin_id' => $id]);
```

事件 → 监听器映射在 `app/event.php` 中配置。添加副作用只需新增 Listener，无需改 Service。

判断标准：操作失败不影响主流程 → 放 Listener；必须成功 → 留在 Service。

## 数据库约定

- 时间戳：`created_at`、`updated_at`、`deleted_at`
- 状态字段：`status`（1=启用，0=禁用）
- 软删除：统一使用 `deleted_at`
- 字符集：utf8mb4

## API 响应格式

```json
{
    "code": 200,
    "message": "操作成功",
    "data": {},
    "timestamp": 1710529800
}
```

分页响应：
```json
{
    "code": 200,
    "data": {
        "list": [],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 100,
            "last_page": 5
        }
    }
}
```

## 前端目录映射

| 目录 | 用途 |
|------|------|
| `src/views/` | 页面组件，按模块组织，自动映射为路由 |
| `src/api/` | API 接口定义，每个模块一个文件 |
| `src/components/` | 通用组件，跨模块复用 |
| `src/hooks/` | 组合式函数（usePaging、useDictOptions 等） |
| `src/store/modules/` | Pinia 状态管理（app/user/settings/multipleTabs） |
| `src/router/` | 路由系统，动态路由由后端菜单数据生成 |
| `src/theme/` | 主题系统，CSS 变量 + Element Plus 样式覆盖 |
| `src/locales/` | 国际化语言包（zh-CN / en-US） |

## 后端目录结构

```
server/
├── app/
│   ├── adminapi/           # 管理后台 API
│   │   ├── controller/v1/  # 控制器（接收请求、调用 Service）
│   │   ├── middleware/      # 中间件（认证、权限、日志）
│   │   ├── route/           # 路由定义
│   │   └── validate/v1/     # 表单验证规则
│   ├── api/                 # 前台用户 API
│   ├── command/             # CLI 命令（代码生成器等）
│   ├── listener/            # 事件监听器（副作用处理）
│   ├── model/               # ORM 模型
│   ├── repository/          # 数据访问层
│   └── service/             # 业务逻辑层
├── core/                    # 框架核心
│   ├── auth/                # JWT 认证（TokenManager）
│   ├── base/                # 基类（Controller/Service/Repository/Model）
│   ├── payment/             # 支付网关（支付宝/微信支付）
│   ├── storage/             # 文件存储（本地/阿里云/腾讯云/七牛）
│   └── wechat/              # 微信公众号集成
├── config/                  # 配置文件
├── database/
│   ├── migrations/          # 数据库迁移
│   └── seeds/               # 数据填充
└── public/                  # Web 入口
    ├── admin/               # 预编译的管理后台前端
    └── install/             # 安装向导
```

## 前端目录结构

```
admin/src/
├── api/                     # API 接口定义
├── assets/                  # 静态资源（图片/图标/字体）
├── components/              # 通用组件
├── constants/               # 常量定义
├── directives/              # 自定义指令（v-has-perm、v-copy）
├── hooks/                   # 组合式函数
├── layout/                  # 布局组件（侧边栏、顶栏、主内容区）
├── locales/                 # 国际化语言包
├── router/                  # 路由（动态路由 + 守卫）
├── store/modules/           # Pinia 状态管理
├── styles/                  # 全局样式
├── theme/                   # 主题系统（CSS 变量 + Element Plus 覆盖）
├── types/                   # TypeScript 类型定义
├── utils/                   # 工具函数
└── views/                   # 页面组件（按模块组织）
```

## 认证流程

1. 登录 → 获取 JWT Token → 存入 localStorage
2. 每次请求 → 拦截器自动添加 `Authorization: Bearer <token>`
3. 401 响应 → 清除 Token → 跳转登录页
4. 权限控制 → `v-has-perm="['permission.name']"` 指令

## 中间件链

管理后台 API 请求经过以下中间件：

1. `admin_auth` — JWT Token 验证，注入 `$request->userId`
2. `admin_permission` — 权限检查
3. `admin_log` — 自动记录 POST/PUT/DELETE 操作日志
