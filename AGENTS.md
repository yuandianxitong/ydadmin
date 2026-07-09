# AGENTS.md — 元点Admin 框架 AI 开发规范

本文件面向 AI 编码代理（Claude Code / Cursor / Windsurf 等）。在本项目中生成或修改代码时必须遵守以下规范。

## 技术栈
- 后端：ThinkPHP 8.0 + PHP 8.0+，MySQL
- 管理端：Vue 3 + TypeScript + Element Plus + Vite + Pinia（admin/）
- PC 端：Nuxt 3 SPA + Naive UI（pc/）

## 分层架构（强制，禁止跨层）

```
请求 → Controller → Service → Repository → Model
                        ↓
                  Listener（事件副作用） / Job（异步队列）
```

| 层 | 目录 | 基类 | 职责 |
|---|---|---|---|
| Controller | `app/adminapi/controller/v1/` | `core\base\Controller` | 接收请求、参数校验、调用 Service、返回响应 |
| Service | `app/service/` | `core\base\Service` | 业务逻辑编排、事务管理、触发事件 |
| Repository | `app/repository/` | `core\base\Repository` | 数据访问封装、所有 ORM 查询集中于此 |
| Model | `app/model/` | `core\base\Model` | ORM 映射、关联关系、访问器/修改器 |
| Listener | `app/listener/{module}/` | — | 事件监听器，处理副作用（日志、通知、缓存清理），按模块分子目录（`system/`、`user/`、`payment/` 等） |
| Validate | `app/adminapi/validate/v1/` | — | 表单验证规则 |

硬性规则：
- Controller 只调用 Service，禁止直接操作 Repository / Model / Db
- Service 只调用 Repository，禁止 Db::table() 和 Model 静态方法（::where/::find/::create）
- Repository 封装所有 ORM 查询，是唯一接触 Model 的层
- 事务（Db::startTrans/commit/rollback）只放 Service 层
- 副作用（日志、通知、缓存清理）通过 $this->trigger('event.name', $data) 交给 Listener

## 依赖注入

Controller 和 Service 的基类都内置了自动 DI。子类只需声明带类型的 `protected` 属性，基类会自动从容器注入：

```php
class AdminService extends Service
{
    protected AdminRepository $adminRepository;   // 自动注入
    protected TokenManager $tokenManager;          // 自动注入
}
```

## 命名与字段约定
- 时间戳字段：created_at / updated_at / deleted_at（软删除统一 deleted_at）
- 状态字段：status（1启用 0禁用）
- 类名 PascalCase，方法 camelCase，数据库字段 snake_case
- Model 定义 getXxxAttr() 访问器时必须同时声明 protected $append = ['xxx']
- 日志类表（只追加）Model 设置 $updateTime = false 和 $deleteTime = false

## API 响应格式

```php
// 成功响应
return $this->success('操作成功', $data);

// 错误响应
return $this->error('错误信息');
```

## Controller 参数校验

```php
// 正确的 validate() 调用签名（第一个参数是数据数组，不是验证类）
$data = $this->request->post();
$this->validate($data, UserManageValidate::class, [], false, 'sceneName');
```

## 优先使用代码生成器
基础 CRUD 必须先用 php think make:crud 生成骨架（Model/Repository/Service/Controller/Validate/Route/前端 API/列表页/表单），AI 只在骨架之上做业务逻辑增量（审核流转、状态机、跨表编排）。禁止绕过生成器手写全套基础 CRUD。

## 新增模块清单
1. make:crud 生成骨架
2. 副作用建 Listener 并注册到 server/app/event.php
3. 新菜单写入 server/public/install/data/init.sql，先检查 menus 已用 ID 避免主键冲突
4. 新表结构同步 server/public/install/data/schema.sql
5. admin/ 源码变更后执行 cd admin && pnpm run build，构建产物 server/public/admin/ 一并提交

## 目录地图

```
server/app/
├── adminapi/     管理端 API：controller/v1、validate/v1、middleware、route
├── api/          前端（小程序/H5/App/PC）API
├── service/      业务逻辑层，按模块分子目录（user、payment、system、article...）
├── repository/   数据访问层，按模块分子目录，与 service 一一对应
├── model/        ORM 模型，按模块分子目录
├── listener/     事件监听器，按模块分子目录（system、user、payment、feedback...）
├── job/          异步队列任务
├── command/      自定义 Console 命令（含 make:crud）
└── event.php     事件 → 监听器映射表

server/core/
├── base/         Controller/Service/Repository/Model/Validate 基类
├── auth/         认证与权限
├── http/         统一响应封装
├── exception/    业务异常、校验异常
├── cache/        缓存封装
├── queue/        队列封装
├── storage/      文件存储封装
├── payment/      支付网关封装
├── wechat/       微信生态封装
├── attribute/    自定义 PHP 属性（Attribute）
└── utils/        通用工具类

admin/src/
├── api/          与后端接口一一对应的 TS 请求函数（admin.ts、user.ts...）
├── views/        页面组件（不是 pages/），按业务模块分目录
├── store/        Pinia store，modules/ 下按模块拆分
├── router/       动态路由：index.ts、routes.config.ts、guards/
├── components/   通用组件
├── hooks/        组合式函数
├── layout/       布局组件
└── utils/        工具函数（含 request.ts 请求封装）
```

## 禁止事项
- 禁止在 Controller / Service 中出现 Db:: 或 Model 静态查询
- 禁止使用 create_time / update_time / delete_time 等旧字段名
- 禁止物理删除有 deleted_at 字段的表数据
- 禁止绕过 Repository 直接查询
- 禁止把前端页面放到 admin/src/pages/（正确位置是 admin/src/views/）
