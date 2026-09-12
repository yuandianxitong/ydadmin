# 元点Admin

基于 [webman](https://www.workerman.net/webman) 的通用后台管理系统（2.x）。常驻内存、多进程：一条命令拉起 HTTP、定时任务、队列与 WebSocket 进程。

> 1.x（ThinkPHP 8 版）仓库为 `ydadmin-tp`，已停止维护。2.x 与 1.x 的数据库不兼容，不提供迁移。

## 目录

| 目录 | 说明 |
|---|---|
| `server/` | webman 后端（PHP 8.4） |
| `admin/` | 管理后台（Vue 3 + Element Plus），构建产物输出到 `server/public/admin` |
| `pc/` | PC 端（Nuxt 3 SPA） |
| `uniapp/` | 移动端（UniApp） |

## 环境要求

PHP 8.4（pdo_mysql、redis、pcntl、posix）· MySQL 8 · Redis · Composer 2 · Node 20+（仅前端开发需要）

## 快速开始

```bash
cd server
composer install
cp .env.example .env    # 填写 DB_* 与 REDIS_*，两个 JWT secret 各用 php -r "echo bin2hex(random_bytes(32));" 生成
php webman db:reset     # 仅开发环境：删库重建并导入 database/install 下的表结构与初始数据
php webman admin:init --username=admin --password=你的密码   # 建立超级管理员（重复执行即重置密码）
php start.php start     # 开发模式（文件变更自动重载）；生产环境用 php start.php start -d
```

访问 `http://127.0.0.1:8000/adminapi/health`，返回 `{"code":200,…}` 即启动成功。

前端开发：

```bash
cd admin && pnpm install && pnpm dev   # 接口代理到 http://127.0.0.1:8000
```

## 部署与升级

### 全新安装（生产）

```bash
mysql -u root -p -e "CREATE DATABASE ydadmin DEFAULT CHARACTER SET utf8mb4"
mysql -u root -p ydadmin < server/database/install/schema.sql   # 先表结构
mysql -u root -p ydadmin < server/database/install/init.sql     # 再初始数据
cd server
composer install
cp .env.example .env    # 见下方说明
php webman admin:init --username=admin --password=你的密码
php start.php start -d
```

`.env` 至少要改：`APP_DEBUG=false`；`DB_*` 与 `REDIS_*`；两个 JWT secret（各 ≥ 32 字节且互不相同）；部署在 nginx 等反向代理后面时填 `TRUSTED_PROXIES`；前端与 API 不同域时填 `CORS_ALLOWED_ORIGINS`。

`php webman db:reset` 会删库重建，只供开发环境使用，`APP_DEBUG` 未开启时拒绝执行。

### Redis

token 吊销（版本号、黑名单）与权限、数据范围缓存都存在 Redis。版本号 key 丢了会重新随机播种，所有旧 token 一律失效，全部管理员需要重新登录（禁用、删除、改密码造成的吊销不会因此复活）；黑名单 key 单独丢失时，已登出的 token 会在过期前重新生效。因此：

- 开启持久化（AOF 或 RDB），并设置 `maxmemory-policy noeviction`，不要让 Redis 淘汰 key；
- 建议给本应用单独一个 Redis DB（`REDIS_DB`），不与其他应用共用；
- 生产环境不要对它执行 `FLUSHDB`，也不要调用 `Cache::clear()`。

### nginx 反向代理

```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

同时把 `TRUSTED_PROXIES` 设为 nginx 的地址（同机部署即 `127.0.0.1`）。只有来自这些地址的请求才读取 `X-Forwarded-For`（取最右侧的非代理地址），登录限流与登录日志按它记录客户端 IP；在代理后面却不配置时，所有请求都会被当成来自代理地址，登录限流会按同一个 IP 计数。

### 升级

`schema.sql` 只用于全新安装。M1 还没有升级脚本，后续里程碑会在 `server/database/` 下提供增量 SQL。不提供从 1.x（ThinkPHP 版）数据的自动迁移。

M1 开发期间各子里程碑会直接修改 `schema.sql`（M1b 新增了字典、操作日志、通知等表，并给 `system_configs` 加了 `is_public` 列），不写迁移。拉取新版本后，开发库执行一次 `php webman db:reset` 重建；测试库会按安装脚本指纹自动重建。

## 质量门禁

在 `server/` 下执行，每个里程碑收尾必须全部通过：

| 命令 | 内容 |
|---|---|
| `composer test` | phpunit 全部套件（unit + redline 安全红线） |
| `composer lint` | php-cs-fixer（`composer lint:fix` 自动修复） |
| `composer analyse` | phpstan level 6 |
| `composer check:context` | 常驻内存纪律：禁止可变静态属性；Service/Controller 禁止 `Db::` 与 Model 静态查询；Repository 查询必须从 `query()` 起手 |
| `composer contract` | 对运行中的服务做接口契约检查（先 `php webman db:reset` 一次，再 `php start.php start -d`） |

测试固定使用 `${DB_NAME}_test` 库与 Redis DB 15，首次运行自动建库；`YDADMIN_TEST_DB_RESET=1 composer test` 可重建测试库。

## 路线图

| 里程碑 | 内容 | 状态 |
|---|---|---|
| M0 | 骨架：统一响应与异常、双 scope JWT、默认拒绝的权限、测试与门禁 | ✅ |
| M1 | 系统核心 + 数据权限 | 进行中（M1a、M1b 已完成：认证、RBAC、数据权限，管理员/角色/菜单/部门，系统配置、数据字典、登录/操作日志、站内通知、仪表盘；M1c 素材与上传待做） |
| M2 | 代码生成器 + API 文档 | |
| M3 | 调度器与队列 | |
| M4 | WebSocket 实时通道 | |
| M5 | 会员与支付 | |
| M6 | 消息与微信 | |
| M7 | 内容与装修 | |
| M8 | 安装与发布 | |
