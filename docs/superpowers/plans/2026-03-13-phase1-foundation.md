# Phase 1: 基础打通 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 打通框架基础设施 — 数据库迁移体系、补全 C 端 API、UniApp 应用基座（请求层 + 登录 + 用户中心）、代码生成器增强、初始化脚本。

**Architecture:** 后端 ThinkPHP 8 分层架构（Controller → Service → Repository → Model），前端 UniApp + Vue 3 + Pinia + Wot UI，C 端 API 路径 `/api/v1/`（由多应用路由自动处理前缀），与 Admin API 共享 Service/Repository 层。

**Tech Stack:** PHP 8.0+ / ThinkPHP 8 / MySQL / Vue 3 / TypeScript / UniApp / Wot UI / Pinia

**Spec Reference:** `docs/superpowers/specs/2026-03-13-framework-optimization-design.md`

---

## 现有 C 端 API 盘点

在开始之前，明确哪些已存在、哪些需要新增：

**已存在的 Controller：**
- `app/api/controller/v1/auth/AuthController.php` — login, smsLogin, wechatLogin, info, logout
- `app/api/controller/v1/user/UserController.php` — profile, updateProfile, changePassword
- `app/api/controller/v1/common/CommonController.php` — config, sendSmsCode, uploadImage
- `app/api/controller/v1/payment/PaymentController.php`
- `app/api/controller/v1/wechat/WechatController.php`

**已存在的中间件：**
- `app/api/middleware/ApiAuthMiddleware.php` — 已注册为 `api_auth`
- `app/api/middleware/ApiRateLimitMiddleware.php` — 已注册为 `api_rate_limit`
- 中间件注册在 `app/api/middleware.php`（非全局 `config/middleware.php`）

**已存在的路由：**
- `app/api/route/auth.php` — login, sms-login, wechat-login, info, logout
- `app/api/route/user.php` — profile(GET/PUT), change-password
- `app/api/route/common.php` — config, sms-code, upload/image
- 路由风格：`'v1.auth.AuthController/login'`，分组无 `api/v1` 前缀（多应用自动处理）

**需要新增的：**
- `register` 注册接口（AuthController + 路由）
- `refreshToken` Token 刷新接口（AuthController + 路由）

---

## Chunk 1: 数据库迁移 + Seeder

### Task 1: 迁移工具配置

**Files:**
- Modify: `server/composer.json`（如需要）

- [ ] **Step 1: 确认 ThinkPHP 迁移工具已安装**

```bash
cd server && php think list | grep migrate
```

如果没有 `migrate` 命令，安装：

```bash
composer require topthink/think-migration
```

- [ ] **Step 2: 确认 seeds 目录存在**

```bash
mkdir -p server/database/seeds
```

- [ ] **Step 3: 验证迁移命令可用**

```bash
cd server && php think migrate:status
```

Expected: 显示迁移状态表（空的）

- [ ] **Step 4: Commit**（仅在安装了新依赖时）

```bash
git add server/composer.json server/composer.lock
git commit -m "chore: add think-migration dependency"
```

---

### Task 2: 基础表迁移（无外键依赖）

创建 13 个无外键依赖的表迁移。

**Files:**
- Create: `server/database/migrations/20260313010000_create_departments_table.php`
- Create: `server/database/migrations/20260313010100_create_roles_table.php`
- Create: `server/database/migrations/20260313010200_create_permissions_table.php`
- Create: `server/database/migrations/20260313010300_create_menus_table.php`
- Create: `server/database/migrations/20260313010400_create_dictionaries_table.php`
- Create: `server/database/migrations/20260313010500_create_system_configs_table.php`
- Create: `server/database/migrations/20260313010600_create_files_table.php`
- Create: `server/database/migrations/20260313010700_create_cron_jobs_table.php`
- Create: `server/database/migrations/20260313010800_create_users_table.php`
- Create: `server/database/migrations/20260313010900_create_payment_orders_table.php`
- Create: `server/database/migrations/20260313011000_create_message_templates_table.php`
- Create: `server/database/migrations/20260313011100_create_wechat_auto_replies_table.php`
- Create: `server/database/migrations/20260313011200_create_notifications_table.php`

**注意：文件名时间戳部分不含下划线，Phinx 要求格式为 `YYYYMMDDHHMMSS_snake_case_name.php`。类名用 CamelCase 对应文件名。**

**Pattern（以 departments 为例）：**

```php
<?php
// 文件名: 20260313010000_create_departments_table.php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateDepartmentsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('departments', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '部门表',
        ]);

        $table
            ->addColumn('parent_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '父级ID'])
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '部门名称'])
            ->addColumn('code', 'string', ['limit' => 50, 'null' => true, 'comment' => '部门编码'])
            ->addColumn('leader', 'string', ['limit' => 50, 'null' => true, 'comment' => '负责人'])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'comment' => '联系电话'])
            ->addColumn('email', 'string', ['limit' => 100, 'null' => true, 'comment' => '邮箱'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '备注'])
            ->addColumn('created_by', 'integer', ['limit' => 10, 'null' => true, 'comment' => '创建人'])
            ->addColumn('updated_by', 'integer', ['limit' => 10, 'null' => true, 'comment' => '更新人'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['parent_id'])
            ->addIndex(['status'])
            ->addIndex(['code'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('departments')->drop()->save();
    }
}
```

**各表字段参考（按此 pattern 创建）：**

| 表名 | 关键字段 | 索引 | 软删除 |
|------|---------|------|--------|
| departments | parent_id, name, code(unique), leader, phone, email, status, sort, remark, created_by, updated_by | parent_id, status, code(unique) | 有 |
| roles | name(unique), title, description(text), data_scope(tinyint), is_system(tinyint), status, sort, created_by, updated_by | name(unique), status, sort | 有 |
| permissions | name(unique), title, group(varchar50), description(text), guard_name(varchar50,default:'admin'), status, sort | name(unique), group, guard_name, status | 有 |
| menus | parent_id(bigint), type(tinyint), title, name, path, component, redirect, icon, permission, is_hidden(tinyint), is_cache(tinyint,default:1), is_affix(tinyint), is_iframe(tinyint), external_link, breadcrumb(tinyint,default:1), active_menu, meta(json), status, sort, created_by, updated_by | parent_id, type, name, path, permission, status, sort | 有 |
| dictionaries | name, code(unique), description(varchar500), status, sort | code(unique), status | 有 |
| system_configs | config_key(unique), config_value(text), config_group(varchar50), config_type(varchar20), config_name, config_desc, config_options(json), config_depends(json), sort_order, status | config_key(unique), config_group, status, sort_order | 有 |
| files | name, path(varchar500), url(varchar500), mime_type, extension(varchar20), size(biginteger,unsigned), group(varchar100), upload_by(integer,unsigned), storage(varchar50) | group, mime_type, upload_by, created_at | 有 |
| cron_jobs | name, command, expression, description, status, last_run_at(datetime), last_result(text), last_status(tinyint), run_count(integer,unsigned), sort, created_by(integer,unsigned) | status | 有 |
| users | nickname(varchar50), avatar, mobile(varchar20,unique), email, password, gender(tinyint), birthday(date), openid(varchar128), unionid(varchar128), mini_openid(varchar128), last_login_ip(varchar45), last_login_time(datetime), login_count, status | mobile(unique), openid, unionid, mini_openid, status | 有 |
| payment_orders | order_no(varchar64,unique), trade_no(varchar128), channel(varchar20), trade_type(varchar20), subject, body(varchar500), total_amount(decimal10,2), refund_amount(decimal10,2), status(varchar20), notify_data(text), extra(text), paid_at(datetime), refunded_at(datetime) | order_no(unique), trade_no, channel, status, created_at | 有 |
| message_templates | name, code(varchar50,unique), sms_enabled(tinyint), sms_template_id, sms_content(varchar500), wechat_official_enabled(tinyint), wechat_official_template_id, wechat_official_url(varchar500), wechat_mini_enabled(tinyint), wechat_mini_template_id, wechat_mini_page(varchar200), variables(json), remark(varchar500), status | code(unique), status | 有 |
| wechat_auto_replies | type(varchar20), keyword(varchar200), match_type(varchar10), reply_type(varchar10), content(text), status, sort_order | type, keyword, status | 有 |
| notifications | title(varchar200), content(text), type(tinyint), sender_id(integer,unsigned,nullable), target_type(tinyint), status | type, sender_id | 有 |

- [ ] **Step 1: 创建 13 个基础表迁移文件**

按上表字段逐个创建，每个文件参照 departments 的 pattern。

- [ ] **Step 2: 运行迁移验证**

```bash
cd server && php think migrate:run
php think migrate:status
```

Expected: 13 张表创建成功，全部显示 up

- [ ] **Step 3: 验证回滚**

```bash
cd server && php think migrate:rollback -t 0
php think migrate:run
```

Expected: 回滚后重新迁移成功

- [ ] **Step 4: Commit**

```bash
git add server/database/migrations/
git commit -m "feat: add database migrations for 13 base tables"
```

---

### Task 3: 依赖表迁移（含关联关系）

创建 10 个有外键依赖或为关联表的迁移。

**Files:**
- Create: `server/database/migrations/20260313020000_create_admins_table.php`
- Create: `server/database/migrations/20260313020100_create_admin_roles_table.php`
- Create: `server/database/migrations/20260313020200_create_role_permissions_table.php`
- Create: `server/database/migrations/20260313020300_create_role_menus_table.php`
- Create: `server/database/migrations/20260313020400_create_dictionary_items_table.php`
- Create: `server/database/migrations/20260313020500_create_admin_login_logs_table.php`
- Create: `server/database/migrations/20260313020600_create_admin_operation_logs_table.php`
- Create: `server/database/migrations/20260313020700_create_cron_job_logs_table.php`
- Create: `server/database/migrations/20260313020800_create_message_logs_table.php`
- Create: `server/database/migrations/20260313020900_create_notification_reads_table.php`

**Pivot 表 Pattern（无自增主键）：**

```php
<?php
// 文件名: 20260313020100_create_admin_roles_table.php

use think\migration\Migrator;

class CreateAdminRolesTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('admin_roles', [
            'id' => false,
            'primary_key' => ['admin_id', 'role_id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '管理员角色关联表',
        ]);

        $table
            ->addColumn('admin_id', 'biginteger', ['signed' => false, 'comment' => '管理员ID'])
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'comment' => '角色ID'])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['admin_id'])
            ->addIndex(['role_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('admin_roles')->drop()->save();
    }
}
```

**各表字段：**

| 表名 | 关键字段 | 索引 | 时间戳 |
|------|---------|------|--------|
| admins | username(varchar50,unique), email(varchar100,unique), mobile(varchar20), password, nickname(varchar50), avatar, department_id(int10), department(varchar100), position(varchar100), last_login_ip(varchar45), last_login_time(timestamp), login_count(int,default:0), status, created_by(bigint), updated_by(bigint) | username, email, mobile, status, created_at, department_id | created_at, updated_at, deleted_at |
| admin_roles | admin_id(bigint,unsigned), role_id(bigint,unsigned) | 复合PK, admin_id, role_id | created_at, updated_at（无deleted_at） |
| role_permissions | role_id(bigint,unsigned), permission_id(bigint,unsigned) | 复合PK, role_id, permission_id | created_at, updated_at（无deleted_at） |
| role_menus | role_id(bigint,unsigned), menu_id(bigint,unsigned) | 复合PK, role_id, menu_id | created_at, updated_at（无deleted_at） |
| dictionary_items | dictionary_id(int,unsigned), label(varchar100), value(varchar100), tag_type(varchar50), description(varchar500), status, sort | dictionary_id, status, unique(dictionary_id,value) | created_at, updated_at, deleted_at |
| admin_login_logs | admin_id(bigint), username(varchar50), ip(varchar45), user_agent(text), login_time(timestamp), login_result(tinyint), login_message(varchar255), browser(varchar100), os(varchar100) | admin_id, username, ip, login_time, login_result | 仅 created_at（无 updated_at, deleted_at） |
| admin_operation_logs | admin_id(bigint), username(varchar50), method(varchar10), path(varchar255), ip(varchar45), user_agent(text), action(varchar100), description(varchar255), params(json), result(json), operation_time(timestamp), execution_time(decimal8,3) | admin_id, username, method, path, action, operation_time | 仅 created_at |
| cron_job_logs | cron_job_id(int,unsigned), status(tinyint), output(text), error(text), started_at(datetime), finished_at(datetime), duration(integer,unsigned) | cron_job_id, created_at | 仅 created_at |
| message_logs | template_id(bigint,unsigned), template_code(varchar50), channel(varchar20), receiver(varchar200), content(text), variables(json), status(tinyint), error_msg(varchar500), sent_at(timestamp) | template_id, channel, status, created_at | created_at, updated_at（无 deleted_at） |
| notification_reads | notification_id(int,unsigned), admin_id(int,unsigned), read_at(datetime) | unique(notification_id,admin_id), admin_id | 仅 created_at（无 updated_at, deleted_at） |

- [ ] **Step 1: 创建 10 个依赖表迁移文件**

- [ ] **Step 2: 运行全量迁移验证**

```bash
cd server && php think migrate:rollback -t 0 && php think migrate:run
php think migrate:status
```

Expected: 23 张表全部 up（13 基础 + 10 依赖，含 3 个 pivot 表）

说明：spec 中提到"21 张表"是指 Model 对应的表，加上 3 个无独立 Model 的 pivot 表（admin_roles, role_permissions, role_menus）共 23 张。

- [ ] **Step 3: Commit**

```bash
git add server/database/migrations/
git commit -m "feat: add database migrations for 10 dependent tables"
```

---

### Task 4: Seeder 数据填充

**Files:**
- Create: `server/database/seeds/DepartmentSeeder.php`
- Create: `server/database/seeds/RoleSeeder.php`
- Create: `server/database/seeds/PermissionSeeder.php`
- Create: `server/database/seeds/MenuSeeder.php`
- Create: `server/database/seeds/DictionarySeeder.php`
- Create: `server/database/seeds/SystemConfigSeeder.php`
- Create: `server/database/seeds/AdminSeeder.php`
- Create: `server/database/seeds/DatabaseSeeder.php`

**执行顺序（DatabaseSeeder 中）：**

```php
<?php
declare(strict_types=1);

use think\migration\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DepartmentSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(MenuSeeder::class);
        $this->call(DictionarySeeder::class);
        $this->call(SystemConfigSeeder::class);
        $this->call(AdminSeeder::class);  // 依赖 department + role
    }
}
```

**各 Seeder 填充数据：**

| Seeder | 数据内容 |
|--------|---------|
| DepartmentSeeder | 默认部门：总部（id=1） |
| RoleSeeder | 超级管理员（id=1, name='super_admin', is_system=1） |
| PermissionSeeder | 核心权限组（system.admin.*, system.role.*, system.menu.* 等） |
| MenuSeeder | 完整菜单树 — **需从现有运行数据库导出**（`SELECT * FROM menus`），这是最复杂的 Seeder |
| DictionarySeeder | 常用字典（性别、状态、是否） |
| SystemConfigSeeder | 基础配置（site_name, site_url, site_logo 等） |
| AdminSeeder | 超级管理员 + admin_roles 关联 |

**AdminSeeder 关键代码：**

```php
<?php
declare(strict_types=1);

use think\migration\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->table('admins')->insert([
            'username'      => 'admin',
            'email'         => 'admin@dev007.com',
            'password'      => password_hash('admin123456', PASSWORD_DEFAULT),
            'nickname'      => '超级管理员',
            'department_id' => 1,
            'status'        => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ])->saveData();

        $this->table('admin_roles')->insert([
            'admin_id'   => 1,
            'role_id'    => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->saveData();
    }
}
```

**MenuSeeder 注意：** 菜单数据最复杂，需要从现有运行中的数据库导出。执行：

```bash
cd server && php think menu:export  # 如果有此命令
# 否则手动从数据库导出 menus 表数据
# 或使用 SQL: mysqldump -u root -p dev007 menus --no-create-info --complete-insert
```

将导出数据转化为 Phinx Seeder 的 insert 格式。

- [ ] **Step 1: 从现有数据库导出 menus 表数据，转为 Seeder 格式**

- [ ] **Step 2: 创建 8 个 Seeder 文件**

- [ ] **Step 3: 验证完整流程**

```bash
cd server
php think migrate:rollback -t 0
php think migrate:run
php think seed:run
```

Expected: 无报错，可用 admin/admin123456 登录管理后台

- [ ] **Step 4: 验证 Admin 登录不受影响**

```bash
curl -X POST http://127.0.0.1:8005/adminapi/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123456"}'
```

Expected: 返回 `{code: 200, data: {token: "...", ...}}`

- [ ] **Step 5: Commit**

```bash
git add server/database/seeds/
git commit -m "feat: add database seeders for initial data"
```

---

## Chunk 2: 补全 C 端 API

大部分 C 端 API 已存在。本 Chunk 只补充缺失的 register 和 refreshToken 接口。

### Task 5: 添加注册接口

**Files:**
- Modify: `server/app/api/controller/v1/auth/AuthController.php`（添加 register 方法）
- Modify: `server/app/api/route/auth.php`（添加注册路由）
- Modify: `server/app/service/user/UserService.php`（添加 register 方法，如不存在）

- [ ] **Step 1: 在 AuthController 中添加 register 方法**

遵循现有代码风格（`declare(strict_types=1)`, `: Response` 返回类型, try-catch, `lang()` 消息）：

```php
/**
 * 注册
 */
public function register(): Response
{
    try {
        $mobile = (string)$this->request->param('mobile', '');
        $password = (string)$this->request->param('password', '');
        $code = (string)$this->request->param('code', '');

        if (empty($mobile) || empty($password) || empty($code)) {
            return $this->error(lang('business.register_fields_required'));
        }

        // 验证短信验证码
        $cacheKey = 'sms_code:register:' . $mobile;
        $cachedCode = cache($cacheKey);
        if (!$cachedCode || $cachedCode !== $code) {
            return $this->error(lang('auth.captcha_invalid'));
        }

        $result = $this->userService->register($mobile, $password, $this->request->ip());

        // 清除验证码
        cache($cacheKey, null);

        return $this->success(lang('messages.register_success'), $result);
    } catch (\Exception $e) {
        return $this->error($e->getMessage());
    }
}
```

- [ ] **Step 2: 在 AuthController 中添加 refreshToken 方法**

```php
/**
 * 刷新Token
 */
public function refreshToken(): Response
{
    try {
        $token = $this->tokenManager->getTokenFromHeader();
        $newToken = $this->tokenManager->refresh($token);
        return $this->success(lang('messages.refresh_success'), ['token' => $newToken]);
    } catch (\Exception $e) {
        return $this->error($e->getMessage());
    }
}
```

- [ ] **Step 3: 更新路由**

在 `server/app/api/route/auth.php` 的无需登录分组中添加：

```php
Route::post('register', 'v1.auth.AuthController/register');
```

在需要登录分组中添加：

```php
Route::post('refresh-token', 'v1.auth.AuthController/refreshToken');
```

- [ ] **Step 4: 确保 UserService 有 register 方法**

检查 `UserService::register()` 是否存在。如不存在，添加：

```php
public function register(string $mobile, string $password, string $ip): array
{
    // 检查手机号是否已注册
    $existing = $this->userRepository->findByMobile($mobile);
    if ($existing) {
        throw new BusinessException(lang('business.mobile_already_registered'));
    }

    $user = $this->userRepository->createUser([
        'mobile'   => $mobile,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'nickname' => '用户' . substr($mobile, -4),
        'status'   => 1,
    ]);

    $this->trigger('user.register', $user);

    // 注册后自动登录
    $token = $this->tokenManager->generate([
        'user_id'  => $user->id,
        'username' => $user->mobile,
        'type'     => 'user',
    ]);

    return [
        'token' => $token,
        'user'  => $this->formatUserInfo($user),
    ];
}
```

- [ ] **Step 5: 添加语言包条目**

在 `server/app/api/lang/zh-cn.php` 和 `en.php` 中补充缺失的语言 key：

```php
// zh-cn.php
'business' => [
    // ...existing...
    'register_fields_required' => '手机号、密码和验证码不能为空',
    'mobile_already_registered' => '该手机号已注册',
],
'messages' => [
    // ...existing...
    'register_success' => '注册成功',
    'refresh_success' => '刷新成功',
],
```

- [ ] **Step 6: 验证新接口**

```bash
# 测试注册（需先发送验证码或暂时绕过验证码验证）
curl -X POST http://127.0.0.1:8005/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"mobile":"13800138000","password":"123456","code":"1234"}'

# 测试登录
curl -X POST http://127.0.0.1:8005/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"mobile":"13800138000","password":"123456"}'

# 测试 Token 刷新（用上面登录返回的 token）
curl -X POST http://127.0.0.1:8005/api/auth/refresh-token \
  -H "Authorization: Bearer <token>"

# 验证现有接口仍然正常
curl -X GET http://127.0.0.1:8005/api/auth/info \
  -H "Authorization: Bearer <token>"
```

- [ ] **Step 7: Commit**

```bash
git add server/app/api/controller/v1/auth/AuthController.php \
        server/app/api/route/auth.php \
        server/app/service/user/UserService.php \
        server/app/api/lang/
git commit -m "feat: add register and refresh-token endpoints to C-end API"
```

---

## Chunk 3: UniApp 基础架构

### Task 6: UniApp 依赖安装 + 项目配置

**Files:**
- Modify: `uniapp/package.json`
- Modify: `uniapp/vite.config.ts`
- Create: `uniapp/.env.development`
- Create: `uniapp/.env.production`

- [ ] **Step 1: 安装依赖**

```bash
cd uniapp
npm install pinia
npm install wot-design-uni
npm install sass sass-loader -D
```

- [ ] **Step 2: 创建环境配置文件**

`.env.development`:
```
VITE_APP_API_URL = http://127.0.0.1:8005
VITE_APP_TITLE = Dev007
```

`.env.production`:
```
VITE_APP_API_URL = https://your-domain.com
VITE_APP_TITLE = Dev007
```

- [ ] **Step 3: 更新 vite.config.ts**

添加 Wot UI 自动导入（参考 Wot UI 官方 UniApp 接入文档配置）。

- [ ] **Step 4: Commit**

```bash
git add uniapp/package.json uniapp/package-lock.json uniapp/vite.config.ts \
        uniapp/.env.development uniapp/.env.production
git commit -m "feat: setup UniApp dependencies (Pinia, Wot UI, Sass)"
```

---

### Task 7: UniApp 工具层

**Files:**
- Create: `uniapp/src/types/api.d.ts`
- Create: `uniapp/src/utils/auth.ts`
- Create: `uniapp/src/utils/request.ts`
- Create: `uniapp/src/utils/platform.ts`
- Create: `uniapp/src/utils/navigate.ts`
- Create: `uniapp/src/utils/validate.ts`

- [ ] **Step 1: 创建 TypeScript 类型定义**

```typescript
// uniapp/src/types/api.d.ts

/** 通用 API 响应 */
export interface ApiResponse<T = any> {
  code: number
  message: string
  data: T
  timestamp: number
}

/** 分页结果 */
export interface PageResult<T = any> {
  list: T[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

/** 用户信息 */
export interface UserInfo {
  id: number
  nickname: string
  avatar: string
  mobile: string
  gender: number
  birthday: string
}

/** 登录响应 */
export interface LoginResult {
  token: string
  user: UserInfo
}
```

- [ ] **Step 2: 创建 Token 管理工具**

```typescript
// uniapp/src/utils/auth.ts

const TOKEN_KEY = 'dev007_token'

export function getToken(): string {
  return uni.getStorageSync(TOKEN_KEY) || ''
}

export function setToken(token: string): void {
  uni.setStorageSync(TOKEN_KEY, token)
}

export function removeToken(): void {
  uni.removeStorageSync(TOKEN_KEY)
}

export function isLoggedIn(): boolean {
  return !!getToken()
}
```

- [ ] **Step 3: 创建请求封装**

```typescript
// uniapp/src/utils/request.ts

import { getToken, removeToken } from './auth'
import type { ApiResponse } from '@/types/api'

const BASE_URL = import.meta.env.VITE_APP_API_URL || ''

interface RequestOptions {
  url: string
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE'
  data?: any
  header?: Record<string, string>
  loading?: boolean
}

function request<T = any>(options: RequestOptions): Promise<T> {
  const { url, method = 'GET', data, header = {}, loading = false } = options

  if (loading) {
    uni.showLoading({ title: '加载中...' })
  }

  const token = getToken()
  if (token) {
    header['Authorization'] = `Bearer ${token}`
  }

  return new Promise((resolve, reject) => {
    uni.request({
      url: `${BASE_URL}${url}`,
      method,
      data,
      header: {
        'Content-Type': 'application/json',
        ...header,
      },
      success: (res: any) => {
        if (loading) uni.hideLoading()

        const response = res.data as ApiResponse<T>

        if (response.code === 200) {
          resolve(response.data)
        } else if (response.code === 401 || res.statusCode === 401) {
          removeToken()
          uni.reLaunch({ url: '/modules/login/pages/login' })
          reject(new Error(response.message || '请先登录'))
        } else {
          uni.showToast({ title: response.message || '请求失败', icon: 'none' })
          reject(new Error(response.message))
        }
      },
      fail: (err: any) => {
        if (loading) uni.hideLoading()
        uni.showToast({ title: '网络异常', icon: 'none' })
        reject(err)
      },
    })
  })
}

export const http = {
  get: <T = any>(url: string, data?: any) => request<T>({ url, method: 'GET', data }),
  post: <T = any>(url: string, data?: any) => request<T>({ url, method: 'POST', data }),
  put: <T = any>(url: string, data?: any) => request<T>({ url, method: 'PUT', data }),
  delete: <T = any>(url: string, data?: any) => request<T>({ url, method: 'DELETE', data }),
}

export default http
```

注意：API 路径使用 `/api/` 前缀（对应后端多应用路由），如 `/api/auth/login`。

- [ ] **Step 4: 创建平台工具**

```typescript
// uniapp/src/utils/platform.ts
// 注意：条件编译 #ifdef 在 .ts 文件中不生效，使用 uni API 判断

export function getPlatform(): string {
  const systemInfo = uni.getSystemInfoSync()
  // #ifdef H5
  return 'h5'
  // #endif
  // #ifdef MP-WEIXIN
  return 'mp-weixin'
  // #endif
  // #ifdef APP-PLUS
  return 'app'
  // #endif
  // fallback: 通过 systemInfo 判断
  if (systemInfo.uniPlatform) {
    return systemInfo.uniPlatform
  }
  return 'unknown'
}

export function isH5(): boolean {
  return getPlatform() === 'h5' || getPlatform() === 'web'
}

export function isWeixin(): boolean {
  return getPlatform() === 'mp-weixin'
}

export function isApp(): boolean {
  return getPlatform() === 'app'
}
```

**注意：** 此文件在运行时可能需要根据实际编译平台调整。条件编译 `#ifdef` 在 `.vue` 和通过 UniApp 构建的 `.ts` 文件中可用，但建议在开发中验证实际效果。如果条件编译不生效，改用 `uni.getSystemInfoSync().uniPlatform` 作为 fallback。

- [ ] **Step 5: 创建路由导航工具**

```typescript
// uniapp/src/utils/navigate.ts

export function navigateTo(url: string, params?: Record<string, any>) {
  const query = params
    ? '?' + Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&')
    : ''
  uni.navigateTo({ url: url + query })
}

export function redirectTo(url: string) {
  uni.redirectTo({ url })
}

export function switchTab(url: string) {
  uni.switchTab({ url })
}

export function navigateBack(delta = 1) {
  uni.navigateBack({ delta })
}

export function reLaunch(url: string) {
  uni.reLaunch({ url })
}
```

- [ ] **Step 6: 创建表单校验工具**

```typescript
// uniapp/src/utils/validate.ts

export const isMobile = (value: string): boolean => /^1[3-9]\d{9}$/.test(value)

export const isPassword = (value: string): boolean => value.length >= 6 && value.length <= 20

export const isVerifyCode = (value: string): boolean => /^\d{4,6}$/.test(value)

export const isEmpty = (value: any): boolean => {
  return value === null || value === undefined || value === ''
}
```

- [ ] **Step 7: Commit**

```bash
git add uniapp/src/utils/ uniapp/src/types/
git commit -m "feat: add UniApp utility layer (request, auth, platform, navigate, validate)"
```

---

### Task 8: UniApp API 层

**先于 Store 创建，因为 Store 依赖 API 层。**

**Files:**
- Create: `uniapp/src/api/auth.ts`
- Create: `uniapp/src/api/user.ts`
- Create: `uniapp/src/api/upload.ts`
- Create: `uniapp/src/api/config.ts`

- [ ] **Step 1: 创建 API 文件**

```typescript
// uniapp/src/api/auth.ts

import http from '@/utils/request'
import type { LoginResult, UserInfo } from '@/types/api'

export const authApi = {
  login: (data: { mobile: string; password: string }) =>
    http.post<LoginResult>('/api/auth/login', data),

  smsLogin: (data: { mobile: string; code: string }) =>
    http.post<LoginResult>('/api/auth/sms-login', data),

  sendSmsCode: (data: { mobile: string }) =>
    http.post('/api/common/sms-code', data),

  wechatMiniLogin: (data: { code: string }) =>
    http.post<LoginResult>('/api/auth/wechat-login', data),

  register: (data: { mobile: string; password: string; code: string }) =>
    http.post<LoginResult>('/api/auth/register', data),

  refreshToken: () =>
    http.post<{ token: string }>('/api/auth/refresh-token'),

  getUserInfo: () =>
    http.get<UserInfo>('/api/auth/info'),

  logout: () =>
    http.post('/api/auth/logout'),
}
```

```typescript
// uniapp/src/api/user.ts

import http from '@/utils/request'
import type { UserInfo } from '@/types/api'

export const userApi = {
  getProfile: () =>
    http.get<UserInfo>('/api/user/profile'),

  updateProfile: (data: Partial<Pick<UserInfo, 'nickname' | 'avatar' | 'gender' | 'birthday'>>) =>
    http.put('/api/user/profile', data),

  changePassword: (data: { old_password: string; new_password: string }) =>
    http.put('/api/user/change-password', data),
}
```

```typescript
// uniapp/src/api/upload.ts

import { getToken } from '@/utils/auth'

const BASE_URL = import.meta.env.VITE_APP_API_URL || ''

export const uploadApi = {
  uploadImage: (filePath: string): Promise<{ url: string; path: string }> => {
    return new Promise((resolve, reject) => {
      uni.uploadFile({
        url: `${BASE_URL}/api/common/upload/image`,
        filePath,
        name: 'file',
        header: { Authorization: `Bearer ${getToken()}` },
        success: (res) => {
          const data = JSON.parse(res.data)
          if (data.code === 200) {
            resolve(data.data)
          } else {
            reject(new Error(data.message))
          }
        },
        fail: reject,
      })
    })
  },
}
```

```typescript
// uniapp/src/api/config.ts

import http from '@/utils/request'

export const configApi = {
  getGlobalConfig: () =>
    http.get<Record<string, any>>('/api/common/config'),
}
```

**注意：** API 路径对应后端现有路由。`/api/` 前缀由 ThinkPHP 多应用路由处理。路径如：
- `/api/auth/login` → `app/api/route/auth.php` 中的 `Route::post('login', ...)`
- `/api/common/config` → `app/api/route/common.php` 中的 `Route::get('config', ...)`
- `/api/common/sms-code` → `Route::post('sms-code', ...)`

- [ ] **Step 2: Commit**

```bash
git add uniapp/src/api/
git commit -m "feat: add UniApp API layer (auth, user, upload, config)"
```

---

### Task 9: UniApp 状态管理 + Hooks

**Files:**
- Modify: `uniapp/src/main.ts`（注册 Pinia）
- Create: `uniapp/src/store/user.store.ts`
- Create: `uniapp/src/store/app.store.ts`
- Create: `uniapp/src/hooks/useAuth.ts`
- Create: `uniapp/src/hooks/usePaging.ts`
- Create: `uniapp/src/hooks/useUpload.ts`
- Create: `uniapp/src/hooks/useShare.ts`

- [ ] **Step 1: 注册 Pinia**

修改 `uniapp/src/main.ts`：

```typescript
import { createSSRApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'

export function createApp() {
  const app = createSSRApp(App)
  const pinia = createPinia()
  app.use(pinia)
  return { app }
}
```

- [ ] **Step 2: 创建 user store**

```typescript
// uniapp/src/store/user.store.ts

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getToken, setToken, removeToken } from '@/utils/auth'
import { authApi } from '@/api/auth'
import type { UserInfo, LoginResult } from '@/types/api'

export const useUserStore = defineStore('user', () => {
  const token = ref(getToken())
  const userInfo = ref<UserInfo | null>(null)

  const isLoggedIn = computed(() => !!token.value)
  const nickname = computed(() => userInfo.value?.nickname || '')
  const avatar = computed(() => userInfo.value?.avatar || '')

  async function login(params: { mobile: string; password: string }): Promise<LoginResult> {
    const result = await authApi.login(params)
    token.value = result.token
    userInfo.value = result.user
    setToken(result.token)
    return result
  }

  async function smsLogin(params: { mobile: string; code: string }): Promise<LoginResult> {
    const result = await authApi.smsLogin(params)
    token.value = result.token
    userInfo.value = result.user
    setToken(result.token)
    return result
  }

  async function getUserInfo(): Promise<UserInfo> {
    const result = await authApi.getUserInfo()
    userInfo.value = result
    return result
  }

  function logout() {
    authApi.logout().catch(() => {})
    token.value = ''
    userInfo.value = null
    removeToken()
    uni.reLaunch({ url: '/modules/login/pages/login' })
  }

  return { token, userInfo, isLoggedIn, nickname, avatar, login, smsLogin, getUserInfo, logout }
})
```

- [ ] **Step 3: 创建 app store**

```typescript
// uniapp/src/store/app.store.ts

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi } from '@/api/config'

export const useAppStore = defineStore('app', () => {
  const config = ref<Record<string, any>>({})
  const isConfigLoaded = ref(false)

  async function getConfig() {
    if (isConfigLoaded.value) return config.value
    const result = await configApi.getGlobalConfig()
    config.value = result
    isConfigLoaded.value = true
    return result
  }

  function getImageUrl(url: string): string {
    if (!url) return ''
    if (url.startsWith('http://') || url.startsWith('https://')) return url
    const baseUrl = config.value.site_url || config.value.oss_domain || ''
    return baseUrl + url
  }

  return { config, isConfigLoaded, getConfig, getImageUrl }
})
```

- [ ] **Step 4: 创建 hooks**

```typescript
// uniapp/src/hooks/useAuth.ts

import { useUserStore } from '@/store/user.store'

export function useAuth() {
  const userStore = useUserStore()

  function checkLogin(): boolean {
    if (!userStore.isLoggedIn) {
      uni.navigateTo({ url: '/modules/login/pages/login' })
      return false
    }
    return true
  }

  return { checkLogin, isLoggedIn: userStore.isLoggedIn }
}
```

```typescript
// uniapp/src/hooks/usePaging.ts

import { ref, reactive } from 'vue'
import type { PageResult } from '@/types/api'

interface PagingOptions<T> {
  fetchFun: (params: any) => Promise<PageResult<T>>
  params?: Record<string, any>
  size?: number
}

export function usePaging<T = any>(options: PagingOptions<T>) {
  const { fetchFun, params = {}, size = 15 } = options

  const pager = reactive({
    page: 1,
    size,
    loading: false,
    finished: false,
    refreshing: false,
    list: [] as T[],
    total: 0,
  })

  async function getList() {
    if (pager.loading || pager.finished) return
    pager.loading = true

    try {
      const result = await fetchFun({
        page_no: pager.page,
        page_size: pager.size,
        ...params,
      })
      if (pager.page === 1) {
        pager.list = result.list
      } else {
        pager.list = [...pager.list, ...result.list]
      }
      pager.total = result.pagination.total
      pager.finished = pager.page >= result.pagination.last_page
      pager.page++
    } finally {
      pager.loading = false
      pager.refreshing = false
    }
  }

  function refresh() {
    pager.page = 1
    pager.finished = false
    pager.refreshing = true
    pager.list = []
    return getList()
  }

  return { pager, getList, refresh }
}
```

```typescript
// uniapp/src/hooks/useUpload.ts

import { uploadApi } from '@/api/upload'

interface UploadOptions {
  maxSize?: number  // MB
}

export function useUpload(options: UploadOptions = {}) {
  const { maxSize = 10 } = options

  function chooseAndUpload(): Promise<string> {
    return new Promise((resolve, reject) => {
      uni.chooseImage({
        count: 1,
        sizeType: ['compressed'],
        success: async (res) => {
          const file = res.tempFiles[0]
          if (file.size > maxSize * 1024 * 1024) {
            uni.showToast({ title: `文件不能超过${maxSize}MB`, icon: 'none' })
            return reject(new Error('文件过大'))
          }

          try {
            const result = await uploadApi.uploadImage(file.path)
            resolve(result.url)
          } catch (e) {
            reject(e)
          }
        },
        fail: reject,
      })
    })
  }

  return { chooseAndUpload }
}
```

```typescript
// uniapp/src/hooks/useShare.ts

import { onShareAppMessage } from '@dcloudio/uni-app'

export function useShare(options?: { title?: string; path?: string; imageUrl?: string }) {
  onShareAppMessage(() => ({
    title: options?.title || '',
    path: options?.path || '/pages/index/index',
    imageUrl: options?.imageUrl || '',
  }))
}
```

**注意：** `useShare` 中的 `onShareAppMessage` 仅在小程序端生效。从 `@dcloudio/uni-app` 导入。

- [ ] **Step 5: TypeScript 编译验证**

```bash
cd uniapp && npx vue-tsc --noEmit
```

Expected: 无类型错误

- [ ] **Step 6: Commit**

```bash
git add uniapp/src/main.ts uniapp/src/store/ uniapp/src/hooks/
git commit -m "feat: add UniApp state management (Pinia) and composable hooks"
```

---

### Task 10: UniApp 页面配置 + 样式 + 基础组件

**Files:**
- Modify: `uniapp/src/pages.json`
- Modify: `uniapp/src/App.vue`
- Create: `uniapp/src/styles/variables.scss`
- Create: `uniapp/src/styles/common.scss`
- Create: `uniapp/src/components/d-page/d-page.vue`
- Create: `uniapp/src/components/d-empty/d-empty.vue`
- Create: `uniapp/src/components/d-list-loader/d-list-loader.vue`

- [ ] **Step 1: 更新 pages.json**

```json
{
  "pages": [
    { "path": "pages/index/index", "style": { "navigationBarTitleText": "首页" } }
  ],
  "subPackages": [
    {
      "root": "modules/login",
      "pages": [
        { "path": "pages/login", "style": { "navigationBarTitleText": "登录", "navigationStyle": "custom" } },
        { "path": "pages/register", "style": { "navigationBarTitleText": "注册" } }
      ]
    },
    {
      "root": "modules/user",
      "pages": [
        { "path": "pages/profile", "style": { "navigationBarTitleText": "个人中心" } },
        { "path": "pages/edit-profile", "style": { "navigationBarTitleText": "编辑资料" } },
        { "path": "pages/change-password", "style": { "navigationBarTitleText": "修改密码" } },
        { "path": "pages/settings", "style": { "navigationBarTitleText": "设置" } }
      ]
    },
    {
      "root": "modules/webview",
      "pages": [
        { "path": "pages/webview", "style": { "navigationBarTitleText": "" } }
      ]
    }
  ],
  "globalStyle": {
    "navigationBarTextStyle": "black",
    "navigationBarTitleText": "Dev007",
    "navigationBarBackgroundColor": "#ffffff",
    "backgroundColor": "#f5f5f5"
  }
}
```

- [ ] **Step 2: 创建全局样式**

```scss
// uniapp/src/styles/variables.scss
$primary-color: #2979ff;
$success-color: #19be6b;
$warning-color: #ff9900;
$danger-color: #fa3534;
$text-color: #333333;
$text-color-secondary: #999999;
$border-color: #ebeef5;
$bg-color: #f5f5f5;
$page-padding: 30rpx;
```

```scss
// uniapp/src/styles/common.scss
@import './variables.scss';

page {
  background-color: $bg-color;
  font-size: 28rpx;
  color: $text-color;
}

.container {
  padding: $page-padding;
}

.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom);
}
```

- [ ] **Step 3: 更新 App.vue**

```vue
<script setup lang="ts">
import { onLaunch } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'

onLaunch(async () => {
  const appStore = useAppStore()
  await appStore.getConfig().catch(() => {})
})
</script>

<style lang="scss">
@import './styles/common.scss';
</style>
```

- [ ] **Step 4: 创建基础组件**

`d-page` — 页面容器（安全区适配），使用 `wd-` 组件
`d-empty` — 空状态（可配图标 + 文案 + 操作按钮）
`d-list-loader` — 列表加载状态（加载中 / 没有更多 / 空列表）

每个组件创建为独立目录下的 `.vue` 文件，使用 Wot UI 组件作为基础。

- [ ] **Step 5: 验证 H5 启动**

```bash
cd uniapp && npm run dev:h5
```

Expected: 浏览器打开首页无报错

- [ ] **Step 6: Commit**

```bash
git add uniapp/src/pages.json uniapp/src/App.vue uniapp/src/styles/ uniapp/src/components/
git commit -m "feat: add UniApp page config, global styles, and base components"
```

---

## Chunk 4: UniApp 登录 & 用户中心模块

### Task 11: 登录模块

**Files:**
- Create: `uniapp/src/modules/login/pages/login.vue`
- Create: `uniapp/src/modules/login/pages/register.vue`
- Create: `uniapp/src/modules/login/composables/useLogin.ts`
- Create: `uniapp/src/components/d-agreement-check/d-agreement-check.vue`

- [ ] **Step 1: 创建 useLogin composable**

```typescript
// uniapp/src/modules/login/composables/useLogin.ts

import { ref } from 'vue'
import { useUserStore } from '@/store/user.store'
import { authApi } from '@/api/auth'
import { isMobile, isPassword, isVerifyCode } from '@/utils/validate'

export function useLogin() {
  const userStore = useUserStore()
  const loading = ref(false)
  const loginType = ref<'password' | 'sms'>('password')
  const countdown = ref(0)

  async function loginByPassword(mobile: string, password: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (!isPassword(password)) {
      uni.showToast({ title: '密码长度6-20位', icon: 'none' })
      return
    }

    loading.value = true
    try {
      await userStore.login({ mobile, password })
      uni.reLaunch({ url: '/pages/index/index' })
    } finally {
      loading.value = false
    }
  }

  async function loginBySms(mobile: string, code: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (!isVerifyCode(code)) {
      uni.showToast({ title: '请输入正确的验证码', icon: 'none' })
      return
    }

    loading.value = true
    try {
      await userStore.smsLogin({ mobile, code })
      uni.reLaunch({ url: '/pages/index/index' })
    } finally {
      loading.value = false
    }
  }

  async function sendCode(mobile: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (countdown.value > 0) return

    await authApi.sendSmsCode({ mobile })
    uni.showToast({ title: '验证码已发送', icon: 'none' })
    countdown.value = 60
    const timer = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) clearInterval(timer)
    }, 1000)
  }

  return { loading, loginType, countdown, loginByPassword, loginBySms, sendCode }
}
```

- [ ] **Step 2: 创建登录页面 login.vue**

使用 Wot UI 组件构建：
- 顶部 Logo + 应用名
- Tab 切换密码/短信登录（`wd-tabs`）
- 手机号输入框（`wd-input`）
- 密码输入框 或 验证码输入框 + 发送按钮
- 登录按钮（`wd-button`）
- 用户协议勾选（`d-agreement-check`）
- 底部注册链接

调用 `useLogin()` composable 处理所有逻辑。

- [ ] **Step 3: 创建注册页面 register.vue**

- 手机号 + 验证码 + 密码 + 确认密码
- 协议勾选
- 注册成功后自动登录跳转首页（调用 `authApi.register()`，返回 token 后存入 store）

- [ ] **Step 4: 创建 d-agreement-check 组件**

```vue
<!-- uniapp/src/components/d-agreement-check/d-agreement-check.vue -->
<template>
  <view class="agreement" @tap="toggle">
    <wd-icon
      :name="modelValue ? 'check-circle-filled' : 'circle'"
      :color="modelValue ? '#2979ff' : '#ccc'"
      size="36rpx"
    />
    <text class="agreement-text">
      我已阅读并同意
      <text class="link" @tap.stop="openUrl(privacyUrl)">《隐私政策》</text>
      和
      <text class="link" @tap.stop="openUrl(termsUrl)">《用户协议》</text>
    </text>
  </view>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: boolean
  termsUrl?: string
  privacyUrl?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

function toggle() {
  emit('update:modelValue', !props.modelValue)
}

function openUrl(url?: string) {
  if (url) {
    uni.navigateTo({ url: `/modules/webview/pages/webview?url=${encodeURIComponent(url)}` })
  }
}
</script>

<style lang="scss" scoped>
.agreement {
  display: flex;
  align-items: center;
  padding: 20rpx 0;
}
.agreement-text {
  font-size: 24rpx;
  color: #999;
  margin-left: 10rpx;
}
.link {
  color: #2979ff;
}
</style>
```

注意：`defineProps` 返回值需要赋给 `props` 变量才能在 `toggle` 中使用，或改用 `defineProps` 的宏形式。

- [ ] **Step 5: 验证登录流程**

```bash
cd uniapp && npm run dev:h5
```

1. 访问登录页面
2. 输入手机号和密码，点击登录
3. 验证跳转到首页
4. 刷新页面，验证 Token 持久化

- [ ] **Step 6: Commit**

```bash
git add uniapp/src/modules/login/ uniapp/src/components/d-agreement-check/
git commit -m "feat: add UniApp login module (password, SMS, register)"
```

---

### Task 12: 用户中心模块

**Files:**
- Create: `uniapp/src/modules/user/pages/profile.vue`
- Create: `uniapp/src/modules/user/pages/edit-profile.vue`
- Create: `uniapp/src/modules/user/pages/change-password.vue`
- Create: `uniapp/src/modules/user/pages/settings.vue`
- Create: `uniapp/src/modules/user/composables/useUser.ts`
- Create: `uniapp/src/components/d-avatar-upload/d-avatar-upload.vue`
- Create: `uniapp/src/modules/webview/pages/webview.vue`

- [ ] **Step 1: 创建 useUser composable**

```typescript
// uniapp/src/modules/user/composables/useUser.ts

import { ref } from 'vue'
import { userApi } from '@/api/user'
import { useUserStore } from '@/store/user.store'
import type { UserInfo } from '@/types/api'

export function useUser() {
  const userStore = useUserStore()
  const loading = ref(false)

  async function loadProfile(): Promise<UserInfo> {
    loading.value = true
    try {
      return await userStore.getUserInfo()
    } finally {
      loading.value = false
    }
  }

  async function updateProfile(data: Partial<UserInfo>) {
    loading.value = true
    try {
      await userApi.updateProfile(data)
      await userStore.getUserInfo()
      uni.showToast({ title: '保存成功' })
    } finally {
      loading.value = false
    }
  }

  async function changePassword(data: { old_password: string; new_password: string }) {
    loading.value = true
    try {
      await userApi.changePassword(data)
      uni.showToast({ title: '修改成功' })
      setTimeout(() => userStore.logout(), 1500)
    } finally {
      loading.value = false
    }
  }

  return { loading, loadProfile, updateProfile, changePassword }
}
```

- [ ] **Step 2: 创建个人中心页面 profile.vue**

- 顶部用户卡片（头像 + 昵称 + 手机号）
- 功能菜单列表（`wd-cell-group`）：编辑资料、修改密码、设置、关于
- 底部退出登录按钮（`wd-button`）

- [ ] **Step 3: 创建编辑资料页面 edit-profile.vue**

- 头像上传（`d-avatar-upload`）
- 昵称（`wd-input`）
- 性别（`wd-picker`）
- 生日（`wd-datetime-picker`）
- 保存按钮

- [ ] **Step 4: 创建修改密码页面 change-password.vue**

- 旧密码 / 新密码 / 确认密码（`wd-input` type="password"）
- 修改成功后 `useUser().changePassword()` 自动退出重新登录

- [ ] **Step 5: 创建设置页面 settings.vue**

- 清除缓存（`wd-cell` 点击清除 uni storage）
- 当前版本
- 隐私政策 / 用户协议链接（跳转 webview）

- [ ] **Step 6: 创建 d-avatar-upload 组件**

圆形头像展示 + 点击触发 `useUpload().chooseAndUpload()` 选图上传 + 返回 URL。

- [ ] **Step 7: 创建 webview 模块**

```vue
<!-- uniapp/src/modules/webview/pages/webview.vue -->
<template>
  <web-view :src="url" />
</template>

<script setup lang="ts">
import { onLoad } from '@dcloudio/uni-app'
import { ref } from 'vue'

const url = ref('')

onLoad((options) => {
  if (options?.url) {
    url.value = decodeURIComponent(options.url)
  }
  if (options?.title) {
    uni.setNavigationBarTitle({ title: decodeURIComponent(options.title) })
  }
})
</script>
```

- [ ] **Step 8: 验证用户中心流程**

```bash
cd uniapp && npm run dev:h5
```

1. 登录后访问个人中心页面
2. 编辑资料并保存
3. 修改密码，验证退出重新登录

- [ ] **Step 9: Commit**

```bash
git add uniapp/src/modules/user/ uniapp/src/modules/webview/ uniapp/src/components/d-avatar-upload/
git commit -m "feat: add UniApp user center module (profile, edit, password, settings)"
```

---

## Chunk 5: 代码生成器增强 + setup.sh

### Task 13: 代码生成器 — 添加迁移文件生成

**Files:**
- Modify: `server/app/command/MakeCrudCommand.php`
- Modify: `server/app/service/system/CodeGeneratorService.php`（实际模板生成逻辑在此）

**注意：** 代码生成的模板逻辑在 `CodeGeneratorService.php` 中，`MakeCrudCommand.php` 是 CLI 入口。需要确认哪个文件包含模板生成逻辑后修改。

- [ ] **Step 1: 在代码生成器中添加迁移文件生成**

核心逻辑：
1. 从已有的 `SHOW FULL COLUMNS` 结果中提取字段信息
2. 将 MySQL 字段类型映射为 Phinx 迁移类型
3. 生成迁移文件到 `database/migrations/`

类型映射表：

```php
$typeMap = [
    'bigint'     => 'biginteger',
    'int'        => 'integer',
    'tinyint'    => 'boolean',
    'smallint'   => 'smallinteger',
    'varchar'    => 'string',
    'char'       => 'char',
    'text'       => 'text',
    'longtext'   => 'text',
    'mediumtext' => 'text',
    'json'       => 'json',
    'decimal'    => 'decimal',
    'float'      => 'float',
    'double'     => 'float',
    'date'       => 'date',
    'datetime'   => 'datetime',
    'timestamp'  => 'timestamp',
];
```

生成文件命名：`{timestamp}_create_{table_name}_table.php`

- [ ] **Step 2: 增强 Validate 生成 — 添加场景化验证**

在 Validate 文件生成模板中添加 `sceneCreate()` 和 `sceneUpdate()` 方法（ThinkPHP 场景验证约定）：

```php
// ThinkPHP 的 scene 方法返回需要验证的字段列表
public function sceneCreate(): array
{
    return ['title', 'content', 'status'];  // 创建时必填字段
}

public function sceneUpdate(): array
{
    return ['id', 'title', 'content', 'status'];  // 更新时需要 id
}
```

- [ ] **Step 3: 增强 TypeScript 类型生成**

在前端 API 文件生成中，补充完整的 Request/Response 接口。生成示例：

```typescript
export interface ArticleCreateReq {
  title: string
  content: string
  status?: number
}

export interface ArticleUpdateReq {
  id: number
  title?: string
  content?: string
  status?: number
}

export interface ArticleInfo {
  id: number
  title: string
  content: string
  status: number
  created_at: string
  updated_at: string
}
```

- [ ] **Step 4: 验证代码生成器**

```bash
cd server
# 创建一个测试表
php think make:crud test_articles --module=content --model=Article
```

检查是否生成了：
- `database/migrations/xxx_create_test_articles_table.php`
- Validate 文件中包含 `sceneCreate()` / `sceneUpdate()` 方法
- 前端 API 文件中包含 TypeScript 接口

验证后清理测试文件。

- [ ] **Step 5: Commit**

```bash
git add server/app/command/MakeCrudCommand.php server/app/service/system/CodeGeneratorService.php
git commit -m "feat: enhance code generator with migration, scene validation, and TypeScript types"
```

---

### Task 14: 创建 setup.sh 初始化脚本

**Files:**
- Create: `setup.sh`
- Verify: `server/.env.example` 存在且完整

- [ ] **Step 1: 创建 setup.sh**

```bash
#!/bin/bash
set -e

echo "========================================="
echo "  Dev007 Framework - 开发环境初始化"
echo "========================================="

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

check_command() {
    if ! command -v "$1" &> /dev/null; then
        echo -e "${RED}✗ $1 未安装，请先安装 $1${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ $1 已安装${NC}"
}

echo ""
echo "检查环境依赖..."
check_command php
check_command composer
check_command node
check_command npm

# 后端初始化
echo ""
echo -e "${YELLOW}[1/6] 初始化后端...${NC}"
cd server

if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}✓ 已复制 .env.example → .env${NC}"
    echo -e "${YELLOW}  请编辑 server/.env 配置数据库连接信息${NC}"
else
    echo -e "${GREEN}✓ .env 已存在，跳过${NC}"
fi

echo ""
echo -e "${YELLOW}[2/6] 安装后端依赖...${NC}"
composer install --no-interaction

echo ""
echo -e "${YELLOW}[3/6] 生成 JWT 密钥...${NC}"
if grep -q "^JWT_SECRET=$" .env 2>/dev/null || grep -q "^JWT_SECRET=your_jwt_secret" .env 2>/dev/null; then
    JWT_SECRET=$(openssl rand -base64 32)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s|^JWT_SECRET=.*|JWT_SECRET=${JWT_SECRET}|" .env
    else
        sed -i "s|^JWT_SECRET=.*|JWT_SECRET=${JWT_SECRET}|" .env
    fi
    echo -e "${GREEN}✓ JWT 密钥已生成${NC}"
else
    echo -e "${GREEN}✓ JWT 密钥已存在，跳过${NC}"
fi

echo ""
echo -e "${YELLOW}[4/6] 数据库迁移 + 种子数据...${NC}"
echo "请确保已在 .env 中配置正确的数据库连接信息"
read -p "是否执行数据库迁移？(y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php think migrate:run
    php think seed:run
    echo -e "${GREEN}✓ 数据库初始化完成${NC}"
else
    echo -e "${YELLOW}⚠ 跳过数据库迁移，请稍后手动执行：${NC}"
    echo "  cd server && php think migrate:run && php think seed:run"
fi

cd ..

echo ""
echo -e "${YELLOW}[5/6] 安装管理后台前端依赖...${NC}"
cd admin && npm install && cd ..

echo ""
echo -e "${YELLOW}[6/6] 安装移动端依赖...${NC}"
cd uniapp && npm install && cd ..

echo ""
echo "========================================="
echo -e "${GREEN}  ✓ 初始化完成！${NC}"
echo "========================================="
echo ""
echo "启动开发服务器："
echo "  后端:   cd server && php think run"
echo "  前端:   cd admin && npm run dev"
echo "  移动端: cd uniapp && npm run dev:h5"
echo ""
echo "默认管理员账号: admin / admin123456"
```

说明：`setup.sh` 面向本地开发环境，与已有的 `deploy.sh`（Docker 生产环境）互补。

- [ ] **Step 2: 设置可执行权限 + 验证 .env.example**

```bash
chmod +x setup.sh
```

确认 `server/.env.example` 包含所有必要配置项（APP_DEBUG, APP_KEY, JWT_SECRET, DB_*, CACHE_*, QUEUE_*）。

- [ ] **Step 3: Commit**

```bash
git add setup.sh
git commit -m "feat: add setup.sh for one-click development environment initialization"
```

---

### Task 15: Phase 1 集成验证

- [ ] **Step 1: 全流程验证**

```bash
# 1. 数据库全量迁移验证
cd server
php think migrate:rollback -t 0
php think migrate:run
php think seed:run
php think migrate:status

# 2. 启动后端
php think run &

# 3. 验证 Admin 登录（现有功能不受影响）
curl -X POST http://127.0.0.1:8005/adminapi/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123456"}'

# 4. 验证 C 端登录
curl -X POST http://127.0.0.1:8005/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"mobile":"13800138000","password":"123456"}'

# 5. 验证 C 端注册（新增接口）
curl -X POST http://127.0.0.1:8005/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"mobile":"13900139000","password":"123456","code":"1234"}'
```

- [ ] **Step 2: UniApp 编译 + 运行验证**

```bash
cd uniapp
npx vue-tsc --noEmit  # TypeScript 类型检查
npm run dev:h5         # 启动 H5 开发服务器
```

浏览器中验证：登录 → 首页 → 个人中心 → 编辑资料 → 修改密码

- [ ] **Step 3: 验证代码生成器增强**

```bash
cd server && php think make:crud test_verify --module=test --model=TestVerify
ls database/migrations/ | grep test_verify  # 确认迁移文件生成
# 清理测试文件
```

- [ ] **Step 4: 最终 Commit**

```bash
git add -A
git commit -m "feat: complete Phase 1 - foundation (migrations, C-end APIs, UniApp base, code generator)"
```

---

## 文件清单总览

### 新建文件（约 45 个）

**后端（~24 个）：**
- `server/database/migrations/` × 23 个迁移文件
- `server/database/seeds/` × 8 个 Seeder 文件

**UniApp（~25 个）：**
- `uniapp/src/types/api.d.ts`
- `uniapp/src/utils/` × 5（request, auth, platform, navigate, validate）
- `uniapp/src/api/` × 4（auth, user, upload, config）
- `uniapp/src/store/` × 2（user.store, app.store）
- `uniapp/src/hooks/` × 4（useAuth, usePaging, useUpload, useShare）
- `uniapp/src/styles/` × 2（variables, common）
- `uniapp/src/components/` × 5（d-page, d-empty, d-list-loader, d-agreement-check, d-avatar-upload）
- `uniapp/src/modules/login/` × 3（login.vue, register.vue, useLogin.ts）
- `uniapp/src/modules/user/` × 5（profile, edit-profile, change-password, settings, useUser）
- `uniapp/src/modules/webview/pages/webview.vue`

**根目录（~3 个）：**
- `setup.sh`
- `uniapp/.env.development`
- `uniapp/.env.production`

### 修改文件（~7 个）
- `server/composer.json`（migration 依赖，如需要）
- `server/app/api/controller/v1/auth/AuthController.php`（添加 register + refreshToken）
- `server/app/api/route/auth.php`（添加两条路由）
- `server/app/service/user/UserService.php`（添加 register 方法，如不存在）
- `server/app/command/MakeCrudCommand.php` 或 `server/app/service/system/CodeGeneratorService.php`（迁移生成）
- `uniapp/src/main.ts`（Pinia 注册）
- `uniapp/src/App.vue`（启动初始化）
- `uniapp/src/pages.json`（页面配置）
- `uniapp/package.json`（依赖）
- `uniapp/vite.config.ts`（Wot UI 配置）
