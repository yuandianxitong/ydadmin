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
    client_max_body_size 100m;
}
```

同时把 `TRUSTED_PROXIES` 设为 nginx 的地址（同机部署即 `127.0.0.1`）。只有来自这些地址的请求才读取 `X-Forwarded-For`（取最右侧的非代理地址），登录限流与登录日志按它记录客户端 IP；在代理后面却不配置时，所有请求都会被当成来自代理地址，登录限流会按同一个 IP 计数。

### 上传与存储

后台的上传接口是 `POST /adminapi/upload/image` 与 `POST /adminapi/upload/file`（multipart 字段名 `file`），任何已登录管理员都能调用，不受角色权限限制；上传成功的文件会收录进「系统管理 → 文件管理」。

**本地存储（默认）**：文件写到 `server/public/storage/uploads/{images|files}/{Ymd}/`，接口返回**相对** URL `/storage/…`，`files.url` 存的就是它——这样换域名不用改库。目录按需创建，`server/public/storage/` 在 `.gitignore` 里不进 git。部署要点：

- 运行 webman 的用户要对 `server/public/` 有写权限；
- **放在 nginx 后面时必须调高 `client_max_body_size`**（上面的反向代理片段里已经是 `100m`）。nginx 默认只有 1 MB，而 `server/config/server.php` 的 `max_package_size` 是 100 MiB、后台还能把单文件上限配到 80 MB；不改这一项的话，超过 1 MB 的上传会被 nginx 直接挡掉并返回它自己的 HTML 413 页面——请求根本到不了 PHP，没有本地化提示，也不会进任何日志，管理员无从诊断；
- **这个目录要和数据库一起备份**，`files` 表只存路径，不存内容；
- 放在 nginx 后面时可以让 nginx 直接吐静态文件，少过一次 PHP（不加也能用，webman 会返回 `public/` 下的文件，并带 `X-Content-Type-Options: nosniff`）：

```nginx
location /storage/ {
    alias /path/to/server/public/storage/;
    add_header X-Content-Type-Options nosniff;
}
```

**云存储**：在「系统管理 → 系统配置 → 存储配置」里把 `storage_driver` 切成 `aliyun`（阿里云 OSS）、`tencent`（腾讯云 COS）或 `qiniu`（七牛），并填好对应的凭据与访问域名。每次上传都现读配置、现建驱动，改完立即生效；凭据填不全时上传直接报业务错误，**不会静默退回本地**。云驱动返回的是完整 URL，已有文件的 `url` 不受切换影响。云凭据不会出现在 `GET /system/config/global` 里（`is_public=0` 加凭据键黑名单两道过滤），只有 `storage_oss_domain` 这类前端拼图片地址要用的键是公开的。

**bucket 的读权限必须设为公共读**（阿里云 OSS 为例：bucket 读写权限选「公共读」；腾讯云 COS、七牛同理，或者绑一个允许匿名读的加速域名）。`files.url` 存的是**持久化的公开地址**（自定义域名，或虚拟主机风格的 `https://{bucket}.{endpoint}/{key}`），不是有效期一小时的签名 URL——这是「不把会过期的签名 URL 写进库」那条决定的直接后果。私有 bucket 下上传会**成功**，当时没有任何报错，但存进 `files.url` 的地址会永久返回 403。

三个云驱动的实现方式不完全一样：阿里云 OSS 与腾讯云 COS 用各自的官方 SDK；**七牛没有用官方的 `qiniu/php-sdk`——它在 autoload 阶段就抛 PHP 8.4 弃用警告**，所以七牛驱动是用 Guzzle 按七牛的签名规则直接发 HTTP 请求实现的，行为与官方 SDK 一致。另外，**阿里云 OSS 与腾讯云 COS 的 SDK 目前都不支持 Guzzle 8，因此依赖实际解析到 Guzzle 7.x**；**仓库自身的约束特意保持 `^7.9 || ^8.0` 不变**——把版本压到 7.x 的是这两个 SDK 各自的约束，`composer.lock` 记录实际解析到的版本，等 SDK 支持 Guzzle 8 时我们的声明不用改。所以不要「顺手」把它收窄成 `^7.9`：那等于把 SDK 当下的限制写成本仓库的政策。

**大小与扩展名**：`storage_image_max_size`（MB，图片接口）、`storage_upload_max_size`（MB，文件接口）、`storage_upload_allowed_ext`（逗号分隔白名单）三项配置实时生效。系统配置页会拒绝把这两个大小配置改到服务器 `max_package_size`（`server/config/server.php`）都装不下的值——那样的话请求在应用代码跑之前就会被 Workerman 断开连接，管理员看到的是一片诊断不出原因的上传失败，而不是本地化的错误文案。另有一份危险扩展名黑名单（`server/core/helper/UploadExtensionGuard.php`，含可执行脚本与 `svg` 等）**优先于白名单**：种子里的 `storage_upload_allowed_ext` 含 `svg`，它同样传不上去，这是有意为之。

### 升级

`schema.sql` 只用于全新安装。M1 还没有升级脚本，后续里程碑会在 `server/database/` 下提供增量 SQL。不提供从 1.x（ThinkPHP 版）数据的自动迁移。

M1 开发期间各子里程碑会直接修改 `schema.sql`，不写迁移：M1b 新增了字典、操作日志、通知等表，并给 `system_configs` 加了 `is_public` 列；M1c 新增了 `files` 表、`storage` 分组的配置种子与文件管理菜单（70–72）。拉取新版本后，开发库执行一次 `php webman db:reset` 重建；测试库会按安装脚本指纹自动重建。开发库忘了重建时，`composer test` 的测试引导会直接提示「请执行 php webman db:reset」，而不是抛一个看不懂的 SQL 错误。

**这道检查要跟着 schema 一起维护**：它靠 `DevDatabaseGuard` 里的 `REQUIRED` 清单逐项核对表与列，**后续里程碑每加一张表或一个列，都要往那份清单里补一行**，否则库过期时它会一声不吭地放行。

## 代码生成器

选一张已存在的数据表，配置字段（控件类型 / 是否列表展示 / 是否表单 / 是否可搜索），一键生成一套 CRUD 模块：Model / Repository / Service / Controller / 路由 / 中英语言包，加前端 API / 列表页 / 表单三个文件，再加一份菜单 SQL。

- **后台页面**：登录后台 → 「开发工具 → 代码生成器」，走完选表 → 配置字段 → 预览 → 生成四步。
- **命令行**：

  ```bash
  cd server
  php webman make:crud gen_articles --module=article --model=GenArticle --comment="文章" --preview   # 先看会生成哪些文件
  php webman make:crud gen_articles --module=article --model=GenArticle --comment="文章"              # 落盘
  php webman make:crud gen_articles --module=article --model=GenArticle --force                        # 覆盖已生成的文件（页面文件永远不覆盖）
  php webman make:crud gen_articles --module=article --model=GenArticle --reload                       # 生成后自动执行一次 reload
  ```

  `--module` 必填，没有默认值——生成器不替你猜一个会变成文件路径与 PHP 命名空间的名字，且不能用系统保留的模块名（`admin_log`/`auth`/`business`/`messages`/`validation`/`generator`；比如 `business`：它会撞上已有的 `resource/lang/{zh_CN,en}/business.php`）。`--model` 缺省按表名推断（去表前缀、去复数 `s`、下划线转大驼峰）；`--comment` 缺省取表注释。

**生成后必须执行一次 `php start.php reload`（或加 `--reload`），新路由才会生效**——webman 是常驻内存进程，`config/route.php` 只在 worker 启动时读一次；生成器只是写文件，不会重启或通知任何进程，写盘完成不等于接口已经可以访问。

生成器只创建新文件，从不修改已有文件：路由是每模块一个 `server/config/route/{module}.php`（`server/config/route.php` 会自动 `require` 这个目录下的所有文件，不用手动挂载）；语言包每模块一份；菜单与按钮权限只生成 SQL（`server/database/generated/{module}-menu.sql`），需要手工执行才会出现在菜单里。目标文件已存在时状态记为「已跳过」，后台页面与 `make:crud` 默认都不会覆盖，除非命令行加 `--force`——页面文件是唯一的例外，永远不会被覆盖，避免冲掉已经手改过的前端页面。

数据权限按表结构自动判定：表里有 `created_by` 列就会生成受数据权限约束的 Repository，同时有 `dept_id` 列则一并启用部门范围；两列都没有则显式声明不受控。

生成器跟随 `APP_DEBUG`：生产环境（`APP_DEBUG=false`）下「选表」「查看字段」两个只读接口仍可用，预览与生成会被拒绝——持有生成权限等于拥有向服务器写 PHP 文件的能力，这条限制避免生产环境暴露这个能力。

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
| M1 | 系统核心 + 数据权限 | ✅（认证、RBAC、数据权限，管理员/角色/菜单/部门，系统配置、数据字典、登录/操作日志、站内通知、仪表盘，素材与上传） |
| M2 | 代码生成器 + API 文档 | |
| M3 | 调度器与队列 | |
| M4 | WebSocket 实时通道 | |
| M5 | 会员与支付 | |
| M6 | 消息与微信 | |
| M7 | 内容与装修 | |
| M8 | 安装与发布 | |
