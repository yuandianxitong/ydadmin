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

| 层 | 目录（均按模块分子目录） | 基类 | 职责 |
|---|---|---|---|
| Controller | `app/adminapi/controller/v1/{module}/` | `core\base\Controller` | 接收请求、参数校验、调用 Service、返回响应 |
| Service | `app/service/{module}/` | `core\base\Service` | 业务逻辑编排、事务管理、触发事件 |
| Repository | `app/repository/{module}/` | `core\base\Repository` | 数据访问封装、所有 ORM 查询集中于此 |
| Model | `app/model/{module}/` | `core\base\Model` | ORM 映射、关联关系、访问器/修改器 |
| Listener | `app/listener/{module}/` | — | 事件监听器，处理副作用（日志、通知、缓存清理） |
| Validate | `app/adminapi/validate/v1/{module}/` | — | 表单验证规则 |

`{module}` 为业务模块名小写（如 `article`、`user`、`brand`）。**五层全部要建模块子目录**，命名空间与目录一致，例如品牌模块：

```
server/app/adminapi/controller/v1/brand/BrandController.php   → namespace app\adminapi\controller\v1\brand;
server/app/service/brand/BrandService.php                     → namespace app\service\brand;
server/app/repository/brand/BrandRepository.php               → namespace app\repository\brand;
server/app/model/brand/Brand.php                              → namespace app\model\brand;
server/app/adminapi/validate/v1/brand/BrandValidate.php       → namespace app\adminapi\validate\v1\brand;
server/app/adminapi/route/brand.php                           （路由文件直接放 route/ 下，没有 v1 子目录）
```

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

## 路由约定

路由文件为 `app/adminapi/route/{module}.php`，控制器引用使用多级目录格式 `v1.{module}.XxxController/方法名`，中间件统一挂 `['admin_auth', 'admin_permission', 'admin_log']`。真实示例（app/adminapi/route/article.php）：

```php
<?php
use think\facade\Route;

// 文章管理
Route::group('article', function () {
    Route::get('list', 'v1.article.ArticleController/list');
    Route::get('detail/:id', 'v1.article.ArticleController/detail');
    Route::post('', 'v1.article.ArticleController/create');
    Route::put(':id/status', 'v1.article.ArticleController/updateStatus');
    Route::put(':id', 'v1.article.ArticleController/update');
    Route::delete(':id', 'v1.article.ArticleController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
```

### {module} 命名推导

1. 取业务主表英文表意，各词转为单数（links→link、logs→log、tags→tag；不可数名词 goods 保留）；
2. `{module}` = 各单数词直接拼接、全小写、不含下划线/连字符；对应 Controller/Service/Repository/Model 主类名 = 各单数词分别首字母大写后拼接（PascalCase）；
3. 优先用地道的业务英文词，不逐字直译表名：如“提现”表 withdrawals 对应 withdraw/Withdraw，不要机械派生成 withdrawal。

参考对照（表名 → `{module}` → 主类名）：

```
tags          → tag          → Tag
friend_links  → friendlink   → FriendLink
stock_logs    → stocklog     → StockLog
goods_skus    → goodssku     → GoodsSku
withdrawals   → withdraw     → Withdraw
member_levels → memberlevel  → MemberLevel
```

### 一个模块内多张表（主表 + 从表）

从表（如 article_categories、coupon_receives、member_benefits）不单独建模块目录，与主表共享同一个 `{module}` 目录（service/repository/model/controller 均放在主表 `{module}` 下），但仍需拥有自己独立完整的 Controller/Service/Repository/Model 四层文件，文件名与类名用该从表单数 PascalCase（如 ArticleCategory、CouponReceive、MemberBenefit），不得只在主表 Service 里笼统处理从表逻辑。路由文件中为从表单独开一个 `Route::group`，组名为 `{主表module}-{从表单数词}`（连字符连接，如 article-category、coupon-receive、memberlevel-benefit），组内控制器引用仍是 `v1.{module}.{从表类名}Controller/方法`（`{module}` 用主表模块目录，不是带连字符的从表组名）。

### 路由动作命名细则

- 主资源的 `Route::group` 分组名必须与 `{module}` 字符串逐字相同、禁止加连字符，即使 `{module}` 本身由多个英文单词拼接而成（正确：stocklog、goodssku、friendlink；错误：stock-log、goods-sku）；连字符只用于上面的从表分组名，或本节下面的多词自定义动作名；
- 只要模块涉及查询、审核、审批等针对已有记录的操作（无论任务是否显式提到“列表”），路由文件必须包含 `Route::get('list', ...)` 这一条基础列表路由（URL 就是 list，不能用 audit-list、pending-list、tree 等变体替代）；模块数据本质是树形结构时（如部门），list 仍要保留，Controller 内部可直接调用 getTree() 实现，tree 只能作为额外别名路由，不能取代 list；
- 状态流转动作用能直接读出业务含义的英文动词命名，取业务动作本身而非流转后的结果状态（如“提交审核”用 submit、“审核发布”用 publish、“下线”用 offline，而不是 pending/published 这类状态名）；
- 自定义动作路由的 URL 必须是「动词紧跟在分组名后面、id 参数放在动词之后」，即 `Route::put('approve/:id', ...)`，不要写成 `Route::put(':id/approve', ...)`（id 在前会导致动词和分组名被 :id 隔断，无法识别为具名动作）；`updateStatus` 是仅用于无额外参数的单纯启用/禁用开关的历史例外，继续用 `:id/status`；
- 若业务描述审核/审批类操作，须同时注册两组路由，不要只选一种：①一个综合动作，动词取业务动作本身（如“审核”→audit），一次请求通过参数区分通过/驳回；②两个专用动作，动词固定用 approve / reject，reject 必须支持必填的原因参数；三者对应的 Controller/Service 方法都要实现；
- 若指令中出现“批量”，除单条操作路由外必须额外提供 `batch-{动词}` 路由（如 batch-pass、batch-reject），对应方法处理 ids 数组；多词动作 URL 一律用连字符命名，对应 Controller 方法名用 camelCase（batchPass、batchReject）。

## 优先使用代码生成器
基础 CRUD 必须先用 php think make:crud 生成骨架（Model/Repository/Service/Controller/Validate/Route/前端 API/列表页/表单），AI 只在骨架之上做业务逻辑增量（审核流转、状态机、跨表编排）。禁止绕过生成器手写全套基础 CRUD。

## 新增模块清单
1. make:crud 生成骨架
2. 副作用建 Listener 并注册到 server/app/event.php
3. 新菜单写入 server/public/install/data/init.sql，先检查 menus 已用 ID 避免主键冲突
4. 新表结构同步 server/public/install/data/schema.sql
5. admin/ 源码变更后执行 cd admin && pnpm run build，构建产物 server/public/admin/ 一并提交

## 数据库升级（框架发行版）
- 唯一机制是 `php think yd:update`：按 `server/database/updates/vX.Y.Z/` 语义化版本顺序执行未应用脚本，自动套用表前缀，已应用版本记入 `system_upgrades` 表（幂等、可断点续跑）。
- 涉及表结构变更时同时更新 `schema.sql`（新装）与 `updates/vX.Y.Z/update.sql`（老用户升级，写**裸表名**、语句幂等）；需要 PHP 逻辑的数据迁移写 `updates/vX.Y.Z/update.php`（返回 `callable(\PDO $pdo, string $prefix)`）。
- 框架**已彻底移除 think-migration 依赖**（`database/migrations/`、`database/seeds/` 已删除），不使用迁移/填充文件管理表结构；安装与升级的 SQL 执行统一走 `core/database/SqlRunner`。用户二开如需迁移工具可自行引入，不影响框架升级。

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
├── command/      自定义 Console 命令（含 make:crud、yd:update）
└── event.php     事件 → 监听器映射表

server/core/
├── base/         Controller/Service/Repository/Model/Validate 基类
├── auth/         认证与权限
├── database/     SqlRunner（前缀改写/语句拆分/执行，安装与 yd:update 升级共用）
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
- 禁止在 Controller / Service 中出现 Db:: 查询或 Model 静态查询（Db::table/Db::query/::where/::find 等；Service 中仅允许事务方法 Db::startTrans/commit/rollback）
- 禁止使用 create_time / update_time / delete_time 等旧字段名
- 禁止物理删除有 deleted_at 字段的表数据
- 禁止绕过 Repository 直接查询
- 禁止把前端页面放到 admin/src/pages/（正确位置是 admin/src/views/）
