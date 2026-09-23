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
php webman db:reset     # 仅可丢弃的开发库：删库重建并导入 database/install 下的表结构与初始数据
php webman admin:init --username=admin --password=你的密码   # 建立超级管理员（重复执行即重置密码）
php start.php start     # 开发模式（文件变更自动重载）；生产环境用 php start.php start -d
```

`db:reset` 会删库重建，只供你自己的可丢弃库使用，`APP_DEBUG` 未开启时拒绝执行。**禁止对 `dev007_ydadmin` 执行 `db:reset`**；现网库表结构变化走 `php webman yd:update`。

访问 `http://127.0.0.1:8000/adminapi/health`，返回 `{"code":200,…}` 即启动成功。已安装前该接口会返回 HTTP 503（`data.installed=false`），请先走 `/install/`。

前端开发：

```bash
cd admin && pnpm install && pnpm dev   # 接口代理到 http://127.0.0.1:8000
```

## 部署与升级

### 全新安装（生产）

```bash
cd server
composer install
cp .env.example .env
php start.php start -d
# 浏览器打开 http://127.0.0.1:8000/install/  或
php webman install -n --db-host=127.0.0.1 --db-name=ydadmin --db-user=root --db-password=... --username=admin --password=...
php start.php restart
```

`.env` 至少要改：`APP_DEBUG=false`；`DB_*` 与 `REDIS_*`；两个 JWT secret（各 ≥ 32 字节且互不相同）；部署在 nginx 等反向代理后面时填 `TRUSTED_PROXIES`；前端与 API 不同域时填 `CORS_ALLOWED_ORIGINS`。向导与 CLI 会写入数据库连接与两把 JWT secret。

安装向导在装完之前未登录可达，所以 `/install/test-connection` 与 `/install/run` 要求带向导页下发的令牌（cookie + `X-Install-Token` 双提交）并校验同源；直接用 curl 打这两条要先 `GET /install/` 取 cookie。

手工灌 SQL 是备选：仅在无法走向导/CLI 时，先建库并依次导入 `schema.sql`、`init.sql`、`regions.sql`，事后须 `touch runtime/install.lock` 与 `php webman yd:update --baseline=2.0.0`。

`php webman db:reset` 会删库重建，只供可丢弃的开发库使用，`APP_DEBUG` 未开启时拒绝执行。**禁止对 `dev007_ydadmin` 执行。**

### Docker Compose

~~~bash
cd server && composer install   # 镜像挂载宿主机 server/，依赖要在宿主机装好
cd ../docker
cp .env.example .env            # 必填三个密码（MYSQL_ROOT_PASSWORD / MYSQL_PASSWORD / REDIS_PASSWORD），端口冲突也只改这里
docker compose up -d --build
# 浏览器 http://127.0.0.1/install/  或
# docker compose exec webman php webman install -n --db-host=mysql --db-name=ydadmin ...
docker compose restart webman   # 装完必须 restart，reload 不够
~~~

compose 的 MySQL 是空库 `ydadmin`，只连服务名 `mysql` / `redis`。禁止把 `DB_HOST` 指到宿主机去打 `dev007_ydadmin`。本机 80/3306/6379 被占时改 `docker/.env` 的端口。

webman 容器默认以 root 跑（它绑挂宿主机的 `server/`，UID 对不上就写不了 `.env` 与 `runtime`）；Linux 上把 `docker/.env` 的 `DOCKER_USER` 设成 `$(id -u):$(id -g)` 就不会再产出 root 属主的文件。

三个密码没有默认值，不填 `docker compose` 直接拒绝启动（早先的 `changeme` 已删）。MySQL 与 Redis 的端口只绑 `127.0.0.1`：compose 的端口映射会绕过宿主机防火墙，绑 `0.0.0.0` 等于把库和 Redis 开到公网，而 Redis 里存着会话吊销名单。确需外部访问再改 `MYSQL_BIND` / `REDIS_BIND`。安装向导里的 Redis 密码要填 `docker/.env` 里那一个。

### 发布包

~~~bash
# 在仓库根，需 Node / pnpm
sh scripts/release.sh           # 产物 dist/ydadmin-2.0.0.zip
unzip dist/ydadmin-2.0.0.zip && cd ydadmin-2.0.0/server
composer install
cp .env.example .env
php start.php start -d
# 浏览器 /install/ 或 php webman install -n ...
php start.php restart
~~~

zip 不含 `vendor/`、`.env`、admin/pc/uniapp 源码和 `docker/`。

### 安装（M8）

- 无表前缀（`DB_PREFIX` 保持空）；安装与升级脚本写裸表名。
- 无演示数据；只灌 `schema.sql`、`init.sql`、`regions.sql` 与超管。
- 装完必须 `php start.php restart`（`reload` 不够），新 `.env` 才会被常驻进程读到。
- 禁止对已有数据的库 DROP：目标库已有表时安装拒绝，库仍在。

### 升级

```bash
cd server
php webman yd:update --dry-run
php webman yd:update
```

首次把已有 2.x 库纳入升级系统时用 `--baseline=2.0.0`（只打标，不重跑基线）。`schema.sql` 只用于全新安装。不提供从 1.x（ThinkPHP 版）数据的自动迁移。

### Redis

token 吊销（版本号、黑名单）与权限、数据范围缓存都存在 Redis。版本号 key 丢了会重新随机播种，所有旧 token 一律失效，全部管理员需要重新登录（禁用、删除、改密码造成的吊销不会因此复活）；黑名单 key 单独丢失时，已登出的 token 会在过期前重新生效。因此：

- 开启持久化（AOF 或 RDB），并设置 `maxmemory-policy noeviction`，不要让 Redis 淘汰 key；
- 建议给本应用单独一个 Redis DB（`REDIS_DB`），不与其他应用共用；
- 生产环境不要对它执行 `FLUSHDB`，也不要调用 `Cache::clear()`。

### 定时任务与队列

`php start.php start` 除了 HTTP 进程，还会拉起：

| 进程 | 数量 | 作用 |
|---|---|---|
| `scheduler` | 1 | 每分钟判定哪些定时任务到点，投递到 `cron-job` 队列；自己不执行任务 |
| `plugin.webman.redis-queue.consumer` | `QUEUE_PROCESS_COUNT`（默认 2） | 消费 `server/app/queue/redis/` 下的快队列：写操作日志（`operation-log`） |
| `plugin.webman.redis-queue.consumer_slow` | `QUEUE_SLOW_PROCESS_COUNT`（默认 1） | 消费 `server/app/queue/redis_slow/` 下的慢队列：执行定时任务（`cron-job`）、外发短信与微信消息（`message-send`） |

**生产环境必须让它们常驻**（`php start.php start -d`，或交给 systemd / supervisor 守护）。只起 HTTP、不起队列进程时：操作日志会堆在 Redis 里不落库，定时任务不会执行，后台点「执行」永远等到超时，短信与微信消息不会发出（站内信同步写入，不受影响）。

**为什么分两个进程组**：定时任务的命令、短信与微信网关调用单次可能要几秒到几十秒（网关超时），与操作日志挤在同一组进程里时会把操作日志堵在后面。慢队列单独一组，互不阻塞；消息量大时调大 `QUEUE_SLOW_PROCESS_COUNT`。

`cron-job` 与 `message-send` 共用同一组 `consumer_slow` 进程（不是各一组）：一条耗时很久的定时任务命令会挤占外发消息的投递，反过来一条卡住的微信调用也会拖慢定时任务。两类负载有一类偏重时，调大 `QUEUE_SLOW_PROCESS_COUNT`。

**从 M6b 之前的版本升级**：`CronJobConsumer` 从 `server/app/queue/redis/` 迁到了 `server/app/queue/redis_slow/`，队列名 `cron-job` 不变，Redis 里尚未消费的任务不受影响。新增的 `consumer_slow` 进程组**必须 `php start.php stop` 再 `php start.php start -d`（或 `restart`）才会拉起**——`reload` 只重启已有进程，不会新增进程组；只 `reload` 的话定时任务与外发消息都没人消费。部署方式如果不会先删掉旧文件再铺新代码（如直接覆盖发布、不清目录的 rsync），必须手动删除旧文件 `server/app/queue/redis/CronJobConsumer.php`：留着它的话，`consumer`（快队列）与 `consumer_slow`（慢队列）两个进程组会同时订阅 `cron-job`，同一条定时任务可能被重复执行。

**操作日志**：请求内投递到 `operation-log` 队列，由队列进程写库；投递失败（如 Redis 不可用）时退回同步写库，日志不丢。写库失败最多重试 3 次（间隔 10、20、30 秒），仍失败进 `failed_jobs`。

**定时任务**：在「系统管理 → 定时任务」维护。

- 表达式是标准 5 段 cron（分 时 日 月 周），按 `server/config/app.php` 的 `default_timezone` 解析；不支持秒级，也不支持 `@hourly` 这类宏。
- scheduler 停机再恢复时，只补最近 5 分钟内错过的触发（`server/config/cron.php` 的 `catchup_minutes`），更早错过的不补跑。
- 多台服务器同时跑 scheduler 不会重复触发（Redis 触发锁）；同一个任务不会重叠执行（执行锁，TTL 为 `lock_ttl`，默认 3600 秒——单次执行可能超过一小时的任务要调大它，否则锁过期后下一次触发会与仍在跑的那次重叠）。
- 定时任务执行失败**不自动重试**（重跑可能重复产生副作用），结果如实写进执行日志。

**命令白名单**：任务的「执行命令」只能以 `server/config/cron.php` 里 `commands` 登记过的控制台命令开头（只看第一个词，后面的参数原样传给命令），在队列进程内执行、不经过 shell。新增一个可调度的命令：

1. 在 `server/app/command/` 写一个带 `#[AsCommand('名字', '说明')]` 的 Symfony Console 命令；
2. 在 `server/config/cron.php` 的 `commands` 里登记 `'名字' => 类名::class`；
3. `php start.php reload`。

**手动执行**：后台点「执行」后，HTTP 进程最多等 `manual_wait_seconds`（默认 10 秒）拿结果；超时会提示「已提交执行，结果请查看执行日志」，任务仍在队列进程里继续跑。等待期间会占住一个 HTTP worker，不要让很多人同时对耗时任务点执行。如果你给 `server/config/redis.php` 配了 `read_timeout`，它必须大于 `manual_wait_seconds`，否则等待会被 Redis 客户端提前打断。

**至多一次**：队列进程先从 Redis 取出任务再执行，进程在执行中被杀掉时正在执行的那一条会丢失——包括 `kill -9`、OOM，以及非平滑重启：`reload` / `stop` 时正在跑的命令超过 `stop_timeout`（`server/config/server.php`，2 秒）就会被强杀。操作日志可能少一条；定时任务这次执行丢失，且它的执行锁 `cron:running:{id}` 没来得及释放：

- 持锁进程在**同一台机器**上：下一次触发或手动执行会发现持锁进程已不存在，自动清掉残留锁照常执行；
- 持锁进程在**另一台机器**上：本机无法判断它是否还活着，锁会保留到 `lock_ttl`（默认 3600 秒）过期，期间定时触发被跳过（记 warning 日志）、手动执行提示「任务正在执行中」。确认那台机器上已没有在跑后，可手动删锁：`redis-cli -n <应用使用的 Redis DB> DEL cron:running:{id}`。

有执行时间较长的命令时，请用平滑重启（等当前任务跑完再退出），不要依赖 2 秒的 `stop_timeout`。

**失败任务**：重试到上限仍失败的任务写进 `failed_jobs` 表（载荷已脱敏）。已知限制：手动执行的载荷里带一个回传结果用的 `token`，脱敏后变成 `***`；这类记录被 `queue:retry` 重投后照常执行并写执行日志，只是结果不会再回到当初那次「执行」请求（它早已超时返回）。

- `cron-job` 队列的失败记录只会在命令**已经执行完之后**产生（命令本身的失败只写执行日志，进 `failed_jobs` 的是写执行日志这类基础设施故障），`queue:retry` 会把命令**再跑一遍**，而且不看任务当前是否已禁用——重投前先确认重复执行没有副作用、任务仍该执行。
- webman/redis-queue 自己也会把每条最终失败的原始任务（**未脱敏**）推进 Redis 列表 `{redis-queue}-failed`，且从不清理。它与 `failed_jobs` 内容重复，核对 `failed_jobs` 后可直接删除：`redis-cli -n <应用使用的 Redis DB> DEL '{redis-queue}-failed'`（配置了队列前缀时键名带前缀）。

```bash
cd server
php webman queue:failed              # 列出最近的失败任务
php webman queue:retry 12            # 重新投递 id=12，成功后删除该行
php webman queue:retry all           # 重新投递全部
php webman queue:flush --days=30     # 删除 30 天前的失败记录（不带 --days 删除全部）
```

### WebSocket 实时通道

`php start.php start` 还会拉起 `websocket` 进程，给管理后台推送实时事件：新通知到达（铃铛即时 +1）、被强制下线、会话被吊销。

| 配置 | 默认 | 说明 |
|---|---|---|
| `WS_LISTEN`（`server/.env`） | `websocket://0.0.0.0:8001` | WebSocket 进程监听地址；放在 nginx 后面时建议设为 `websocket://127.0.0.1:8001`，只让 nginx 访问 |
| `WS_PROCESS_COUNT`（`server/.env`） | `1` | WebSocket 进程数；多进程时每个进程只投递自己持有的连接 |
| `VITE_APP_WS_URL`（`admin/.env.production`） | 空 | 前端连接地址前缀，如 `wss://admin.example.com`；留空时按当前页面地址推导为 `ws(s)://当前域名/ws` |

**nginx**：前端默认连 `ws(s)://当前域名/ws`，在「nginx 反向代理」的配置里再加一段：

```nginx
location = /ws {
    proxy_pass http://127.0.0.1:8001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_read_timeout 120s;
}
```

`location = /ws` 是精确匹配，只把 `/ws` 这一个地址转给 WebSocket 进程，不会误吞 `/ws` 开头的其他路径；`proxy_read_timeout` 必须大于前端心跳间隔（25 秒）；不加 `Upgrade` / `Connection` 两个头时握手会失败，前端会一直退回轮询。开发时 `pnpm dev` 已把 `/ws` 代理到 `ws://127.0.0.1:8001`。

**鉴权**：浏览器的 WebSocket 不能带 `Authorization` 头，前端先带登录 token 调 `POST /adminapi/ws/ticket` 换一张票据，再连 `/ws?ticket=…`。票据 30 秒内有效、只能用一次，JWT 不会出现在 URL 与访问日志里。

**投递**：业务进程把事件发到 Redis 频道 `realtime:admin`，每个 WebSocket 进程订阅后投给自己持有的目标连接。**多台服务器部署时所有实例必须连同一个 Redis**，否则连在另一台上的管理员收不到推送。推送是**至多一次**：WebSocket 进程与 Redis 断线重连期间的事件不补发——通知仍以数据库为准（前端重连后会主动补拉未读数），强制下线另有 token 版本号兜底。

**在线管理员**：「系统管理 → 在线管理员」列出当前开着后台页面（有 WebSocket 连接）的管理员。在线状态存 Redis，页面关闭后立即移除；进程崩溃来不及清理时最多 90 秒后自动消失。

**强制下线**：踢掉该管理员的**全部**会话——所有 token 立即失效（下一次请求即 401），打开着的页面收到提示后跳转登录页。不能踢自己，也不能踢超级管理员。禁用、删除管理员、修改密码、登出同样会让已打开的页面在一次吊销复查间隔（1 分钟）内断开；后台静默刷新 token 沿用同一个会话 id，不会断开连接。

| 关闭码 | 含义 | 前端行为 |
|---|---|---|
| `4000` | 心跳超时（90 秒没有收到 ping） | 自动重连 |
| `4001` | 票据无效或已过期 | 重新取票据重连，最多 3 次 |
| `4003` | 会话已被吊销或被强制下线 | 不重连，跳转登录页 |

### nginx 反向代理

```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    client_max_body_size 100m;
}
```

同时把 `TRUSTED_PROXIES` 设为 nginx 的地址（同机部署即 `127.0.0.1`）。只有来自这些地址的请求才读取 `X-Forwarded-For`（取最右侧的非代理地址），登录限流、登录日志与按 IP 的短信验证码闸门（见「会员与资产（M5a）」）都按它记录客户端 IP；在代理后面却不配置时，所有请求都会被当成来自代理地址，这几项都会按同一个 IP 计数——验证码闸门的后果最重，见 M5a 小节。

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

### 会员与资产（M5a）

C 端新增 `/api` 路由组：`auth/{login,register,sms-login,refresh-token,info,logout}`、`common/sms-code`、`user/{profile,change-password,balance,points,balance-logs,points-logs}`。除登录、注册、短信登录与短信验证码四条公开接口外，其余都要求 `Authorization: Bearer <user token>`——user scope 的 token 与管理端 admin scope 相互独立、互不通用，载荷是 `{user_id, ver}`。管理端把某会员状态改为禁用、会员自己改密码，都会让该会员名下已签发的 token 立即失效（下一次请求即 401），不用等 token 自然过期。

**升级到 M5a 时要先执行开发库补丁 SQL（建 `users`/`balance_logs`/`points_logs` 三表与菜单、`sms` 配置），再部署代码**；仪表盘统计接口直接查 `users` 表、不做缺表兜底（部署脚本必然建表，缺表就该报错而不是悄悄显示一堆 0 掩盖破损的部署），顺序反了会导致管理端仪表盘报错。

**短信验证码**：在「系统管理 → 系统配置」维护 `sms` 分组的七个键：

| 配置键 | 说明 |
|---|---|
| `sms_driver` | `aliyun`（阿里云）或 `tencent`（腾讯云） |
| `sms_access_key` / `sms_access_secret` | 短信服务商的 AccessKey / AccessSecret（腾讯云对应 SecretId / SecretKey） |
| `sms_sign_name` | 短信签名，需在服务商后台报备 |
| `sms_sdk_app_id` | 仅 `sms_driver=tencent` 时需要 |
| `sms_template_login` / `sms_template_register` | 登录、注册两个场景各自的短信模板 ID |

**改完短信配置需要 `php start.php reload` 才生效**：短信驱动经容器绑定注入，php-di 的定义默认共享，一个 worker 进程内只解析一次。这与存储不同——存储改 `storage_driver` 是即时生效的（`StorageManagerTest::test_disk_follows_storage_driver_config_without_restart` 钉住了这一点）。换服务商或换凭据后记得 reload，否则会以为改了没用。

M5a 只开放 `login`、`register` 两个验证码场景，改密码、绑定/换绑手机号留给 M6 接消息模板体系时再开。任意一项配置缺失时，接口返回统一文案而不是把服务商报错原文透给前端；服务商网关报错的原文只写日志、不对外展示（错误信息里常带 AccessKey 片段与签名细节）。composer 只装了阿里云 SDK（`alibabacloud/dysmsapi-20170525`）；把 `sms_driver` 切到 `tencent` 前需要先手动装 `tencentcloud/sms`，SDK 缺失时驱动会直接抛业务错误提示先装包，不会静默失败。

**验证码限流，以及它对 `TRUSTED_PROXIES` 的硬依赖**：发送侧按手机号限流（60 秒 1 次、每日 10 次），另有一道**按客户端 IP 的闸门（每小时 20 次）**——公开接口只按手机号限流的话，换个号就能绕过，直接代价是运营方的短信费；校验侧则限制同一手机号连续输错的次数（超过 5 次即把该验证码作废，必须重新获取），否则公开的短信登录接口就是一个 5 分钟的撞码窗口。

IP 闸门取客户端 IP 的方式与登录限流完全同源：**只有直连地址属于 `TRUSTED_PROXIES` 时才读 `X-Forwarded-For`**。所以**部署在 nginx 等反向代理后面却没有正确配置 `TRUSTED_PROXIES` 时，所有真实用户都会被解析成同一个代理 IP、共用同一个「每小时 20 次」的桶**：第 21 位用户开始就再也发不出验证码，而前端只会看到一句「当前网络请求验证码过于频繁」，日志里也没有别的线索，极难诊断。反向代理后面务必按上面「nginx 反向代理」一节把这一项配对。

**边界**：微信登录（网页扫码、小程序、小程序手机号快捷登录、公众号 H5）与绑定手机号见「微信登录（M6a）」一节；余额充值与支付见「支付（M5b）」。

**并发测试的硬依赖**：`AssetConcurrencyTest`（余额/积分行锁并发用例）需要 PHP 的 `pcntl` 与 `posix` 扩展，缺扩展的 CI 镜像跑到这个测试会直接报错——这是有意的失败模式，不要把它改成跳过。

### 支付（M5b）

C 端余额充值：`POST /api/user/recharge`（`amount` 元、`channel` 为 `wechat` / `alipay`，端类型由请求头 `X-Client-Type` 决定）与 `GET /api/payment/query?order_no=`；支付回调 `POST /api/payment/notify/wechat`、`POST /api/payment/notify/alipay` 公开、不要求 token。C 端**没有**通用下单接口，也**没有**退款接口——退款只能在服务器上用命令行执行（见下）。

**升级到 M5b 时先执行开发库补丁 SQL**（`payment_orders`、`refund_orders` 两张表，`payment` 分组 15 个配置键，两条定时任务），再部署代码。

**各端能用的支付方式**：

| `X-Client-Type` | 微信支付 | 支付宝 |
|---|---|---|
| `pc` | Native（二维码） | 电脑网站支付（表单跳转） |
| `h5` | H5 支付 | 手机网站支付 |
| `app` | APP 支付 | APP 支付 |
| `wechat_h5` | JSAPI（公众号内） | 不支持 |
| `miniapp` | JSAPI（小程序） | 不支持 |

JSAPI 需要会员的 openid（`users.oa_openid` / `users.mini_openid`），由微信登录写入（见「微信登录（M6a）」）；没有对应 openid 时这两端选微信支付会提示「请先完成微信授权后再支付」。各端下单使用各自的 appid（小程序 `wechat_mini_app_id`、公众号 H5 `wechat_official_app_id`、pc `wechat_open_app_id`，为空时回退 `pay_wechat_app_id`），这些 appid 都要在微信支付商户后台与商户号绑定；回调里的 appid 按该订单下单时实际使用的 appid 核对。H5 支付上报的用户 IP 与登录限流同源，**反向代理后面必须配好 `TRUSTED_PROXIES`**（见「nginx 反向代理」）。

**配置**：「系统管理 → 系统配置 → 支付配置」。改完**立即生效，不需要 reload**（每次下单、查单、回调都现读配置、现建驱动；这点与短信不同）。

| 配置键 | 说明 |
|---|---|
| `pay_alipay_enabled` | 开启支付宝（只控制新下单；查单、关单、退款、回调不看开关） |
| `pay_alipay_sandbox` | 请求支付宝沙箱网关，仅联调用，生产务必关闭 |
| `pay_alipay_app_id` | 开放平台应用 AppID |
| `pay_alipay_private_key` | 应用私钥（RSA2）正文，可带或不带 PEM 头尾行 |
| `pay_alipay_public_key` | 支付宝公钥（公钥模式；不支持公钥证书模式） |
| `pay_alipay_notify_url` | 回调地址，留空则用 `site_url` + `/api/payment/notify/alipay` |
| `pay_wechat_enabled` | 开启微信支付（同上，只控制新下单） |
| `pay_wechat_app_id` | 与商户号绑定的 AppID |
| `pay_wechat_mch_id` | 商户号 |
| `pay_wechat_api_v3_key` | APIv3 密钥（32 字节） |
| `pay_wechat_serial_no` | **商户** API 证书序列号（不是平台证书序列号） |
| `pay_wechat_private_key_path` | 商户私钥文件 `apiclient_key.pem` 的路径，相对路径按 `server/` 解析 |
| `pay_wechat_public_key_id` / `pay_wechat_public_key` | 微信支付公钥模式的公钥 ID（`PUB_KEY_ID_` 开头）与公钥正文，两项同填或同空 |
| `pay_wechat_notify_url` | 回调地址，留空则用 `site_url` + `/api/payment/notify/wechat` |

**回调地址**：渠道服务器要能从公网访问到它，微信要求 `https`。留空时拼接用的是「基础配置」里的 `site_url`，**不看请求的 Host 头**（Host 头可被伪造）；`site_url` 还是默认的 `http://localhost` 时回调必然收不到——此时订单仍会由 `GET /api/payment/query` 的补查（同一订单每 10 秒最多查一次网关）或关单任务确认入账，但到账会变慢。回调验签失败时微信得到 HTTP 500、支付宝得到 `fail`，渠道会按自己的策略重试。

**微信的两种验签模式**：

- **微信支付公钥模式**（新商户默认）：填 `pay_wechat_public_key_id` 与 `pay_wechat_public_key`。回调的 `Wechatpay-Serial` 必须等于公钥 ID，否则直接拒绝；不下载任何证书。
- **平台证书模式**：两项都留空。首次需要验签时调用 `GET /v3/certificates` 下载平台证书，缓存在 `server/runtime/cert/wechatpay/{商户号}/`（按商户号隔离，运行 webman 的用户需要写权限）。遇到未知序列号时最多每 60 秒重新下载一次，仍对不上就拒绝回调。

**入账**：回调、查单补查、关单任务三处只经同一个入口把订单置为已支付，锁订单行、核对渠道与金额（分）后，在同一个事务里给会员加余额并写一条「充值」流水（来源 `payment:{订单号}`）。同一订单重复回调只入账一次；金额或渠道对不上时不改库、应答失败并记 `error` 日志。

**定时任务**（随补丁 SQL 写入，可在「系统管理 → 定时任务」里查看）：

| 任务 | 命令 | 表达式 | 作用 |
|---|---|---|---|
| 支付订单超时关闭 | `payment:close-expired` | `*/5 * * * *` | 订单下单 30 分钟未支付即过期；过期超过 1 分钟的，先查网关（已支付的补记入账），否则调网关关单再本地置为已关闭；单轮至多 100 单 |
| 退款结果对账 | `payment:reconcile-refunds` | `*/10 * * * *` | 查询创建超过 2 分钟仍在处理中的退款并结算；网关查不到的退款超过 30 分钟判失败并把余额加回；处理中超过 24 小时记 `error` 日志，需人工到商户后台核对 |

两条都需要 scheduler 与队列进程常驻（见「定时任务与队列」）。

**退款（仅命令行）**：

```bash
cd server
php webman payment:refund R20260916120000123456 10.00 --reason="用户申请退款"
```

- 金额单位是元，允许部分退款，累计不超过实付金额；同一订单有退款在处理中时拒绝再退。
- 充值订单退款会**先扣会员余额**（余额不足直接拒绝，什么都不写），再调网关；网关明确失败时写一条「退款失败冲正」流水把余额加回，结果不确定时保持「处理中」交给对账任务。
- 退出码：`0` 退款成功、`1` 失败（含前置校验失败）、`2` 处理中。执行人按系统用户名记为 `cli:{用户名}`。
- `payment:refund` **不在** `server/config/cron.php` 白名单里，不能配成定时任务——这是有意的，不要加进去。

**支付宝沙箱联调**：在开放平台「沙箱应用」取 AppID、配置应用公私钥并拿到支付宝公钥，填进上面几项并打开 `pay_alipay_sandbox`，用沙箱买家账号付款。本地开发时回调到不了本机，充值结果靠 pc 端轮询 `payment/query` 的补查确认。微信支付没有可用的沙箱，只有离线测试覆盖。

### 微信登录（M6a）

C 端微信登录与绑定，全部为 `/api` 下的公开接口（`bind-oa-openid` 除外）。**不依赖任何微信 SDK**：后端直接调用 `api.weixin.qq.com`，access_token 缓存在 Redis（`wechat:access_token:{appid}`，按 appid 隔离，多个 worker 同时过期时只有一个去刷新）。

| 接口 | 场景 | 未匹配到已有账号时 |
|---|---|---|
| `POST /api/auth/wechat-web-login {code}` | pc 端开放平台扫码 | 自动注册（无手机号，昵称/头像尽力取自微信） |
| `POST /api/auth/wechat-login {code}` | 小程序静默登录 | 自动注册（无手机号） |
| `POST /api/auth/wechat-quick-login {code}` | 小程序快捷登录 | 返回 `need_bindphone` 与一次性 `temp_token`（5 分钟） |
| `POST /api/auth/wechat-bindphone {temp_token, phone_code}` | 小程序授权手机号 | 手机号已有账号则绑定到该账号，否则注册 |
| `POST /api/auth/wechat-h5-login {code}` | 微信内 H5（公众号静默授权） | 返回 `need_login`，**不注册**；用户登录后再绑定 |
| `GET /api/wechat/oauth-url?redirect_url&scope` | H5 发起公众号授权 | —— |
| `POST /api/user/bind-oa-openid {oa_openid}`（需 user token） | H5 登录后绑定公众号 openid | —— |
| `GET /api/common/config` | 前端公开配置（含 `wechat_open_app_id`） | —— |

**升级到 M6a 时先执行开发库补丁 SQL**（`wechat_official` / `wechat_mini` / `wechat_open` 三组 19 个配置键、「渠道」目录与三个配置页菜单、`payment_orders.app_id` 列），再部署代码。

**配置**：管理端「渠道 → 公众号 / 小程序 / 开放平台 → 配置」（页面保存走系统配置接口，权限是 `system.config.list` / `system.config.update`）。改完**立即生效，不需要 reload**。

| 配置键 | 用于 |
|---|---|
| `wechat_open_app_id` / `wechat_open_app_secret` | pc 扫码登录（开放平台「网站应用」）；`wechat_open_app_id` 会经 `common/config` 公开给 pc |
| `wechat_mini_app_id` / `wechat_mini_app_secret` | 小程序静默登录、快捷登录、手机号解密 |
| `wechat_official_app_id` / `wechat_official_app_secret` | 公众号 H5 授权 |
| `wechat_official_token` / `_aes_key` / `_encrypt_type`、`wechat_mini_msg_token` / `_msg_aes_key` / `_msg_format` / `_encrypt_type` | 服务器消息推送，M6c 才使用；M6a 只保存 |

**账号匹配**：先按本端 openid 列（网页 `openid`、小程序 `mini_openid`、公众号 `oa_openid`）找；找不到且微信返回了 unionid，再按 unionid 找——只有该账号本端列**为空**时才补写，已有别的值时**不覆盖**、视为未匹配。同一 openid 的首次登录/注册有 Redis 锁，双击不会注册出两个账号。被禁用的账号返回「账户已被禁用」。

**公众号 openid 绑定为什么要 cookie**：`wechat-h5-login` 未匹配时，在同一响应里下发签名的 HttpOnly cookie `yd_oa_bind`（HMAC-SHA256，密钥派生自 `JWT_USER_SECRET`，有效 7 天，`Path=/api; SameSite=Lax`，`site_url` 为 https 时加 `Secure`）。`bind-oa-openid` 只在 cookie 有效且其中的 openid 与请求一致时才绑定，且不覆盖已有绑定、不抢占别的账号已绑的 openid。1.x 直接信任客户端传来的 openid，任何人都能把别人的 openid 绑到自己账号上。

**报错对外只说三类**：「微信登录未配置」「微信授权失败，请重试」「微信服务暂不可用，请稍后重试」；微信的 errcode / errmsg 只写 `warning` 日志。响应里不会出现 session_key、access_token 与原始手机号报文。

**已知限制**：

- **OAuth `state` 不校验**：pc 端由前端自己拼授权链接（固定 `state=pc_login`），uniapp 回跳后先剥掉 `state` 再调后端，不改前端就无法校验，存在登录 CSRF 风险。
- **H5 与 API 必须同域部署**：绑定 cookie 靠浏览器随请求自动携带；H5 与 API 分属不同域名时 cookie 不会被带上，绑定一律失败（fail closed，JSAPI 支付会继续提示授权）。
- **cookie 过期后需要清本地存储**：uniapp 把 openid 存在本地存储后不再重新授权；用户超过 7 天才登录时绑定会失败，需清除该 H5 的本地存储（或微信内「清除缓存」）后重新进入。
- **同一 unionid 从两个不同客户端并发首登可能产生两个账号**：登录锁是按「本端列 + openid」加的（如小程序按 `mini_openid`、pc 按 `openid`），不是按 unionid 加锁；小程序与 pc 同一微信账号在同一时刻各自首次登录时，两把锁互不冲突，会各自按本端 openid 注册一个账号，都补写同一个 unionid（不冲突，因为各自是各自账号的首次补写）。
- pc 扫码登录回跳后会丢失 `?redirect=` 参数（前端行为）。
- `wechat_open_app_id` 未配置时 `common/config` 不返回该键，pc 登录页据此显示「未配置」。

**真实环境自测**（本地无法端到端验证：微信回调域名必须是公网已备案域名；仓库只有离线测试，没有 Mock 网关——调试开关误开到生产会让任何人冒充任意 openid 登录）：

1. 部署到公网 https 域名，「基础配置」`site_url` 填该域名。
2. **pc 扫码**：开放平台创建网站应用、授权回调域填该域名，填 `wechat_open_*`；打开 `/pc/login` 点微信登录，扫码后应直接登录。
3. **小程序**：小程序后台配置服务器域名，填 `wechat_mini_*`；真机上快捷登录 → 授权手机号 → 登录；再用同一微信静默登录应直接进入同一账号。
4. **公众号 H5**：认证服务号设置网页授权域名，填 `wechat_official_*`；在微信里打开 `/mobile/`，首次应静默授权后跳到登录页，短信登录后在库里核对 `users.oa_openid` 已写入；随后在该 H5 发起微信支付应能调起 JSAPI。
5. 检查日志里没有 appsecret、code、access_token、手机号明文。

### 公众号运营（M6c）

公众号服务器 URL 接入、自定义菜单代理、自动回复。消息回调**同步**回包，不进队列，也不走 `{code, message, data}` 统一响应体。**不引入微信 SDK**：验签与加解密在 `core/wechat` 自写，菜单三个接口直接调 `api.weixin.qq.com`。

**升级到 M6c 时先执行开发库补丁 SQL**（`wechat_auto_replies` 表，「自定义菜单」410–412、「自动回复」420–423；**不种**按钮 401），再部署代码。渠道配置页的 Token / AESKey / 加密方式从本里程碑开始真正使用（M6a 只保存）。

**服务器 URL**：微信公众号后台「设置与开发 → 基本配置 → 服务器配置」必须与系统配置对齐：

| 微信后台 | 系统配置 | 说明 |
|---|---|---|
| URL | `{site_url}/api/wechat/serve` | `site_url` 是「基础配置」里的站点地址，**不看请求的 Host 头**；微信要求公网 `https` |
| Token | `wechat_official_token` | 接入校验与消息签名 |
| EncodingAESKey | `wechat_official_aes_key` | 43 位；明文模式可不填 |
| 消息加解密方式 | `wechat_official_encrypt_type` | `1` 明文、`2` 兼容、`3` 安全，**必须与微信后台一致** |

三项在管理端「渠道 → 公众号 → 配置」维护。改完**立即生效，不需要 reload**（`WechatConfigResolver::officialServer()` 每次现读，不缓存一层）。登录用的 `wechat_official_app_id` / `_app_secret` 与消息 Token 互不影响：只配了登录、还没配 Token 时，H5 登录和 M6b 模板消息照常可用。

接入验证失败时先查：Token 是否两边一致、URL 是否 HTTPS、反代有没有改写 query（`signature` / `timestamp` / `nonce` / `echostr`）。`GET /api/wechat/serve` 在缺签、错签或未配置时一律 HTTP 200、空 body，**绝不回 echostr**；`POST` 验签或解密失败同样空 body，验签通过后的业务异常记日志并回 `success`，不回异常原文。

**自动回复**：管理端「渠道 → 公众号 → 自动回复」。三种类型：关键词（`keyword`）、关注（`subscribe`）、默认（`default`）。用户发文本，或点自定义菜单的 click 按钮时，`EventKey` 与文本 `Content` **走同一套关键词匹配**（精确 → 模糊 → 默认回复）。**没有启用的关注规则就不回**（不会写死「感谢关注！」）。本阶段只回文本（`reply_type` 固定 `text`，客户端传图文会被丢掉）。启用中的关注回复、默认回复各只能有一条。

**自定义菜单**：管理端「渠道 → 公众号 → 自定义菜单」。只代理微信（读 `get_current_selfmenu_info`、写 `menu/create`、删 `menu/delete`），**不落库、没有本地草稿**。刷新页会丢掉未发布的编辑（前端既有行为）。未配 AppID/Secret 时发布菜单会业务失败。

**已知限制**：

- **无粉丝页、无图文回复、无菜单草稿**：粉丝列表 / 用户信息 / 管理端发模板消息接口不注册；按钮 **401 不种**（M6b 已有按模板给会员发公众号消息）。表留了 `reply_type`，本阶段只写 `text`。
- **回复按 2000 字符截在校验**：微信文本上限按字节算，超长中文仍可能被微信拒（菜单名称同理，先本地结构校验再交给微信）。
- **`wechat_mini_msg_*` 继续只存不用**：小程序消息推送端点本里程碑不做。
- **不改前端**：admin 菜单页与自动回复页沿用 1.x；click 能回，是因为 serve 认 `CLICK`，不是前端新做了事件配置页。
- M6b 模板消息通道与本里程碑无调用关系（一个主动推、一个被动回）。

**真实环境自测**（需要已认证服务号与公网 https；仓库只有离线测试，没有 Mock 网关）：

1. 部署到公网 https 域名，「基础配置」`site_url` 填该域名。
2. 「渠道 → 公众号 → 配置」填 Token / EncodingAESKey / 加密方式，与微信后台完全一致；服务器 URL 填 `{site_url}/api/wechat/serve`，提交接入验证应通过。
3. 配一条关注回复后用微信关注（或取消再关注），应收到该文本；关掉关注规则后再关注，应没有回复。
4. 配精确 / 模糊关键词，在公众号里发文本，以及点 click 类型自定义菜单，应命中同一套规则。
5. 把加密方式改成安全模式（`3`）并与微信后台同步，再发一条文本，应仍能收到回复。
6. 管理端自动回复可增删改查；再启用第二条关注回复应被拒并有明确文案。自定义菜单页能打开即可；未配 AppID 时发布失败**不算**冒烟失败。
7. 检查应用日志里没有 Token、AESKey、access_token、用户消息正文或完整 XML。

### 消息体系（M6b）

会员注册与充值成功时，按**消息模板**向会员发送站内信、短信、公众号模板消息、小程序订阅消息。

**升级到 M6b 时先执行开发库补丁 SQL**（`message_templates`、`message_logs`、`user_notifications`、`user_notification_reads` 四张表，2 个内置模板，「消息管理」菜单 130–135），再部署代码，并按上一节**重启**（不是 reload）拉起 `consumer_slow`。

**触发点与变量**：

| 模板 code | 何时发送 | 变量 |
|---|---|---|
| `user_register`（注册成功通知） | 手机号注册成功；pc 扫码、小程序静默登录、小程序授权手机号三条微信注册路径新建账号时 | `nickname` |
| `payment_success`（充值成功通知） | 充值订单真正置为已支付时（重复回调不重复发） | `order_no`、`amount`（元，两位小数）、`paid_at`（`Y-m-d H:i:s`） |

两个模板是内置的，不可删除；可以停用（状态关）或逐个通道关闭。发送在业务事务**提交之后**进行：事务回滚不会发消息；消息体系的任何故障（模板表不可用、Redis 不可用、网关报错）只写日志，**不影响注册响应与支付回调应答**。

**四个通道**：

| 通道 | 发送条件 | 接收人 | 发送方式 |
|---|---|---|---|
| 站内信 | 模板「站内信」启用 | 会员本人 | 同步写入，C 端「消息」页可见 |
| 短信 | 启用、填了短信模板 id、会员有手机号 | `users.mobile` | 异步（`message-send` 队列），走「短信配置」里的网关 |
| 公众号模板消息 | 启用、填了模板 id 与字段映射、会员有 `oa_openid` | 公众号 openid | 异步，用 `wechat_official_*` 配置 |
| 小程序订阅消息 | 启用、填了模板 id 与字段映射、会员有 `mini_openid` | 小程序 openid | 异步，用 `wechat_mini_*` 配置 |

通道启用但缺字段映射时，不发送，记一条失败日志「未配置字段映射」；会员没有对应接收人时直接跳过，不记日志。

**模板维护**：管理端「系统管理 → 消息管理 → 消息模板」可以新增、编辑、停用模板，表单能改名称、状态、备注、三个外发通道的开关与模板 id、公众号跳转 URL、小程序跳转页面。以下几项**表单里没有，只能直接写库**（管理端前端未改，表单保存不会覆盖它们）：

- `wechat_official_data` / `wechat_mini_data`：微信模板字段映射，JSON 对象，键是微信模板的字段名，值是带占位符的文本；
- `site_enabled` / `site_title` / `site_content`：站内信开关与标题正文；
- `variables`：变量说明 `[{"key", "name", "example"}]`，**短信参数按它的 key 顺序组装**（腾讯云按顺序取值）。

内置模板的映射是示例值，**必须按你在微信后台实际选用的模板字段改**，例如：

```sql
UPDATE message_templates
SET wechat_official_data = JSON_OBJECT('character_string1', '${order_no}', 'amount2', '${amount}元', 'time3', '${paid_at}'),
    wechat_official_template_id = '你的公众号模板ID',
    wechat_official_enabled = 1
WHERE code = 'payment_success';
```

（`.env` 配了 `DB_PREFIX` 时表名带前缀。）占位符写作 `${变量名}`，变量名只能是小写字母、数字、下划线；渲染时缺变量按失败处理。微信字段值按字段名前缀截断：`thing` 20 字、`character_string` / `number` / `letter` 32、`symbol` / `phrase` 5、`amount` / `name` 10、`time` / `date` 30、`phone_number` 17、`car_number` 8，其它 20。

**重试规则**：

- **确定失败不重试**：公众号 43004（未关注）、小程序 43101（未订阅）、40003 / 40037 / 47003 等参数类错误、通道未配置、模板中途停用或通道被关、会员接收人被清空。
- **暂时失败重试**：微信 `-1`（系统繁忙）、`45009`（调用超限）、网络故障与超时；access_token 失效（40001 / 40014 / 42001）先刷新 token 立即重试一次。重试由队列完成，**最多 3 次**，间隔为 `server/config/plugin/webman/redis-queue/redis.php` 的全局 `retry_seconds`；仍失败时日志置失败（「重试耗尽」），并写进 `failed_jobs`。
- **短信一律不重试**：`core/sms` 的两个驱动把配置缺失、网关拒绝、网络异常都包成同一种异常，分不清；而且网络异常时短信可能已经送达网关，重试会让会员重复收到短信。
- 并发或重复投递同一条**已处理完**的日志时只会发送一次：消费时先锁读日志行，已不是「待发」就跳过。所以对 `message-send` 执行 `php webman queue:retry` **不会重发**（那条日志已置失败）；确需重发，请让业务重新触发。但这不是严格的「至多一次」：结果用 `WHERE status=0` 条件写回，若网关已经发送成功、写回这一步又失败（如数据库瞬断），日志会留在「待发」被队列重试，导致会员在这个窄窗口内收到重复消息（至少一次投递）。
- 慢队列进程在外发过程中被杀掉（如 `stop` 时正卡在一次慢微信调用上、`kill -9`、OOM）时，那条日志已经加过 `attempts` 但停在「待发」（`status=0`），既不会被同一次消费重试，也没有自动巡检把它捞回来，会一直停在「待发」。这类日志需要人工核实：「消息管理 → 消息日志」按状态筛「待发送」找出来，确认是否已经送达，再决定是否让业务重新触发。

**消息日志**：「系统管理 → 消息管理 → 消息日志」。`receiver` 是遮蔽后的展示值（手机号 `138****1234`，openid 前 6 位加 `…`，站内信为 `user#会员id`）；`error_msg` 只含 errcode、固定短语与异常类名。**日志与应用日志里不会出现完整手机号、openid、access_token**。

**C 端站内信接口**（需 user token）：

| 接口 | 说明 |
|---|---|
| `GET /api/message/list?page_no&page_size` | 本人站内信，按时间倒序，行含 `is_read` |
| `GET /api/message/unread-count` | `{count}` |
| `POST /api/message/read {ids?}` | 不传或传空数组：本人全部标为已读；传 id：只处理属于本人的，其余静默忽略 |

**已知限制**：

- 管理端界面无法编辑微信字段映射、站内信文案与变量说明（前端未改），自定义模板需要直接写库。
- 小程序订阅消息需要用户在小程序里逐次授权订阅；当前 uniapp 没有发起订阅请求，该通道会以 43101 失败并记日志。
- 公众号模板消息只能发给已关注公众号的用户，未关注时以 43004 失败。
- 管理端日志页的「渠道」筛选只有短信、公众号、小程序三项（前端未改）：站内信日志能列出，但不能按渠道单独筛选，渠道列显示原始值 `site`。
- 不支持向全体会员广播站内信，也没有管理端「测试发送」。
- 短信验证码不走消息模板，仍用「短信配置」里的 `sms_template_login` / `sms_template_register`。

**真实环境自测**（需要真实凭据，仓库只有离线测试）：

1. 「短信配置」填好网关与签名；把 `user_register` 的短信通道打开、填短信模板 id（模板变量须与 `variables` 的 key 一致），手机号注册一个新会员，应收到短信，日志状态为成功。
2. 公众号：按上文 SQL 为 `payment_success` 填映射与模板 id 并启用；用已关注公众号且已绑定 `oa_openid` 的会员充值，应收到模板消息。
3. 在「消息日志」核对状态与失败原因；在应用日志里确认没有完整手机号、openid、access_token。

### 内容管理（M7a）

文章栏目、文章、公告、协议、用户反馈。五张表：`article_categories`、`articles`、`announcements`、`agreements`、`feedbacks`。管理端菜单 7 / 700–744（内容管理 → 协议、公告、反馈、文章资讯）。

**升级到 M7a 时先执行开发库补丁 SQL**（五表、菜单 7/700–744、两条协议种子、`feedback_received` 模板），再部署代码。

**C 端接口**：

| 接口 | 鉴权 | 说明 |
|---|---|---|
| `GET /api/article/list`、`/api/article/detail/{id}` | 公开 | 只出已发布；详情浏览量 +1 |
| `GET /api/article-category/list` | 公开 | 仅启用栏目树 |
| `GET /api/announcement/list`、`/api/announcement/detail/{id}` | 公开 | 只出已发布 |
| `GET /api/agreement/{code}` | 公开 | 仅启用；种子 `user_agreement` / `privacy_policy` |
| `POST /api/feedback/submit`、`GET /api/feedback/list`、`/api/feedback/detail/{id}` | 需 user token | 只读写本人 |

浏览量只在 C 端已发布文章详情增加；后台详情不加。`publish_at` 只展示、不参与 C 端过滤。

**反馈站内信**：提交成功后经 `afterCommit` 发 `feedback_received`（只开站内信）。改文案在「消息管理 → 消息模板」即可，不必改代码。回复、关闭、删除不发消息。提交限：正文 2000 字、图片 9 张，同一会员每分钟 5 条（`config/feedback.php` 的 `submit_per_minute`），超了返回 code 429。

**已知限制**：

- 无置顶、无定时发布；表没有 `is_top` / `published_at` 列（PC 类型里的这两个字段对不上）。
- 反馈 `status=1`（处理中）本里程碑无写入入口。
- 不改 PC / uniapp 页面。

### 应用管理（M7b）

地区、App 版本、数据导入。三张表：`regions`、`app_versions`、`data_imports`。管理端菜单 8 / 800–813（应用管理 → 区域管理、应用版本）。不种导入菜单，也不种 `region.status` / `version.status` 按钮。

**升级到 M7b 时先执行开发库补丁 SQL**（三表、菜单 8/800–813），再部署代码。地区种子是大陆 31 省市区（`id =` GB/T 2260 **六位**码，3347 行）。新装走 `regions.sql`（`INSERT`）；已有库用 `php webman yd:update` 补齐缺失行（`INSERT IGNORE`，不覆盖已改名称），同时删掉早先种进去的 82 行 9 位街道码（东莞、中山、儋州、嘉峪关这几个不设区的市）。已知限制：升级不会改名或移动已有行，所以老库里的废弃区划与旧名称会留着。

**C 端接口**（公开，不挂会员认证）：

| 接口 | 鉴权 | 说明 |
|---|---|---|
| `GET /api/region/tree` | 公开 | 启用地区树，节点 `value/label/children` |
| `GET /api/region/children` | 公开 | 指定父级下的启用子级 |
| `GET /api/version/check?platform&version_code` | 公开 | 该平台启用行里 `version_code` 最大的一条；大于当前才 `need_update` |

**级联**：管理端登录即可 `GET /adminapi/common/regions`，一次拉全量启用树（节点 `{value,label,children?}`）。`admin/src/components/Region` 已在打这条。

**数据导入**：`POST /adminapi/dataimport/upload` + `GET /adminapi/dataimport/history` 是通用 CSV 管道，只计数、记历史，不进 `files`，且**拒收 `module=user`**——会员导入只走用户列表上的 `POST /adminapi/user/import`（权限 `user.import`）。通用 `dataimport.*` 不进角色树，非超管默认 403。

导入整份同步跑在请求里：单次上限 5000 行，超了在写任何一行之前就拒收；GBK 文件（中文 Windows 上 Excel 的默认编码）会自动转码；失败行只记业务文案（原始异常带着绑定值与库主机，只进应用日志），`errors` 最多留 200 条并在末尾标明实际失败行数；临时 CSV 读完即删。

**已知限制**：

- 除 `user` 外的 module 仍不写业务表（没有商品模块）。
- 通用导入权限不进角色树，非超管调 `dataimport.*` 会 403。
- 地区树 / `common/regions` 一次拉全量启用节点，不做按需加载。
- 地区种子可被超管删改；不在本里程碑做「种子地区不可删」。
- 不改 admin / PC / uniapp 页面。

### 装修（M7c）

DIY 页面装修、链接库、移动端主题与 tabBar。四张表：`diy_pages`、`diy_page_versions`、`diy_links`、`mobile_configs`。管理端菜单 16 / 1600–1618（装修 → 页面装修、自定义页面、底部导航、主题风格、链接管理）。

**草稿与已发布分开存**：`page_settings` 是草稿，`page_settings_published` 才是 C 端读的那份，发布时才拷过去（回滚同理）。升级到该行为要执行 `php webman yd:update`（v2.0.2 加列并用现有值回填）。首页即使经 `/adminapi/diy/pages/home/*` 这条通用路径写，也要求 `diy.home.*` 权限。

**升级到 M7c 时先执行开发库补丁 SQL**（四表、菜单 16/1600–1618、home/member 与 `mobile_configs` 种子），再部署代码。补丁按 `page_key` / 是否已有配置行判空再插，不会覆盖开发库里已有的同 key 装修。

**C 端接口**（公开，不挂会员认证）：

| 接口 | 鉴权 | 说明 |
|---|---|---|
| `GET /api/mobile/diy-page?key=` | 公开 | 只出已发布且启用、组件树非空的页；缺 `key` → 400；未发布 / 不存在 → 404，body 不含草稿 |
| `GET /api/mobile/config` | 公开 | 主题色、tabBar，以及 `home_decoration`（已发布首页，没有则为 `null`） |

**组件**：15 个内置（`banner`、`nav-grid`、`category-nav`、`rich-text`、`title-bar`、`divider`、`image-ad`、`image-cube`、`video`、`notice`、`search-bar`、`float-button`、`user-info-card`、`service-menu`、`content-list`）。`widgets.plugins` 恒为空数组。`content-list` 的 `source=latest` 会注水已发布文章。

**页面列表**：`GET /adminapi/diy/pages` 出参是 `{list,total}`，不是标准 `{list, pagination}`（前端已冻）。

**已知限制**：

- 无插件宿主；`plugins` 空，以后另开里程碑。
- 不做 `platform=pc` 第二套页面（列保留，本里程碑只写 `uniapp`）。
- 页面列表不是标准四键分页。
- 种子图路径是 `/static/diy/...`；2.x 若缺文件，C 端显示破图，不在本里程碑补图。
- 前端 MobileConfig 上的客服 / 分享 / 微信字段本里程碑不落库（不建 `app_intro` / `service_*` / `share_*` / `wechat_appid`）。
- 不改 admin / PC / uniapp 页面。

### 开发库与 schema 演进

`schema.sql` 只用于全新安装。生产升级走上方「升级」的 `php webman yd:update`，升级目录的写法与可重跑要求见 `server/database/updates/README.md`。不提供从 1.x（ThinkPHP 版）数据的自动迁移。

M1 开发期间各子里程碑会直接修改 `schema.sql`，不写迁移：M1b 新增了字典、操作日志、通知等表，并给 `system_configs` 加了 `is_public` 列；M1c 新增了 `files` 表、`storage` 分组的配置种子与文件管理菜单（70–72）。M3 新增了 `failed_jobs`、`cron_jobs`、`cron_job_logs` 三张表、定时任务菜单（90–95）与一条示例定时任务（每天 03:00 执行 `log:archive --days=90`）。拉取新版本后，**现网库（含 `dev007_ydadmin`）只执行 `php webman yd:update`**，禁止 `db:reset`。可丢弃的本地库才用 `php webman db:reset` 重建；测试库会按安装脚本指纹自动重建。可丢弃库忘了重建时，`composer test` 的测试引导会提示执行 `db:reset`，而不是抛一个看不懂的 SQL 错误。

**这道检查要跟着 schema 一起维护**：它靠 `DevDatabaseGuard` 里的 `REQUIRED` 清单逐项核对表与列，**后续里程碑每加一张表或一个列，都要往那份清单里补一行**，否则库过期时它会一声不吭地放行。

## 代码生成器

选一张已存在的数据表，配置字段（控件类型 / 是否列表展示 / 是否表单 / 是否可搜索），一键生成一套 CRUD 模块：Model / Repository / Service / Controller / 路由 / 中英语言包，加前端 API / 列表页 / 表单三个文件，再加一份菜单 SQL。

- **后台页面**：登录后台 → 「开发工具 → 代码生成器」，走完选表 → 配置字段 → 预览 → 生成四步。
- **命令行**：

  ```bash
  cd server
  php webman make:crud gen_articles --module=article --model=GenArticle --comment="文章" --preview   # 先看会生成哪些文件
  php webman make:crud gen_articles --module=article --model=GenArticle --comment="文章"              # 落盘
  php webman make:crud gen_articles --module=article --model=GenArticle --force                        # 覆盖已生成的文件（两个前端页面文件永远不覆盖）
  php webman make:crud gen_articles --module=article --model=GenArticle --reload                       # 生成后自动执行一次 reload
  ```

  `--module` 必填，没有默认值——生成器不替你猜一个会变成文件路径与 PHP 命名空间的名字，且不能用系统保留的模块名（`admin_log`/`auth`/`business`/`messages`/`validation`/`generator`；比如 `business`：它会撞上已有的 `resource/lang/{zh_CN,en}/business.php`）。`--model` 缺省按表名推断（去表前缀、去复数 `s`、下划线转大驼峰）；`--comment` 缺省取表注释。

**生成后必须执行一次 `php start.php reload`（或加 `--reload`），新路由才会生效**——webman 是常驻内存进程，`config/route.php` 只在 worker 启动时读一次；生成器只是写文件，不会重启或通知任何进程，写盘完成不等于接口已经可以访问。

生成器只创建新文件，从不修改已有文件：路由是每模块一个 `server/config/route/{module}.php`（`server/config/route.php` 会自动 `require` 这个目录下的所有文件，不用手动挂载）；语言包每模块一份；菜单与按钮权限只生成 SQL（`server/database/generated/{module}-menu.sql`），需要手工执行才会出现在菜单里。目标文件已存在时状态记为「已跳过」，后台页面与 `make:crud` 默认都不会覆盖，除非命令行加 `--force`——两个前端页面文件（列表页 `index.vue` 与表单组件 `{Model}Form.vue`）是例外，永远不会被覆盖，避免冲掉已经手改过的前端代码。

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
| `composer contract` | 对已启动且已安装的服务做接口契约检查（脚本本身不重置数据库；表结构变了才对可丢弃库 `db:reset`，现网库走 `yd:update`） |

测试固定使用 `${DB_NAME}_test` 库与 Redis DB 15，首次运行自动建库；`YDADMIN_TEST_DB_RESET=1 composer test` 可重建测试库。

## 路线图

| 里程碑 | 内容 | 状态 |
|---|---|---|
| M0 | 骨架：统一响应与异常、双 scope JWT、默认拒绝的权限、测试与门禁 | ✅ |
| M1 | 系统核心 + 数据权限 | ✅（认证、RBAC、数据权限，管理员/角色/菜单/部门，系统配置、数据字典、登录/操作日志、站内通知、仪表盘，素材与上传） |
| M2 | 代码生成器 + API 文档 | ✅（按表生成 CRUD 模块与 `make:crud`，由路由与校验规则推导的 OpenAPI 文档） |
| M3 | 调度器与队列 | ✅（scheduler 进程按 cron 表达式自动执行白名单命令，执行日志与手动执行；redis-queue 队列进程，操作日志异步落库；`failed_jobs` 与 `queue:failed/retry/flush`） |
| M4 | WebSocket 实时通道 | ✅（websocket 进程 + 一次性票据握手，按管理员定向推送；通知实时推送与指定管理员通知；在线管理员页与强制下线；被吊销会话自动断开） |
| M5 | 会员与支付 | ✅（C 端认证与短信验证码、余额与积分及管理端会员管理；微信支付 v3 与支付宝充值、回调验签与同事务入账、超时关单、命令行部分退款与退款对账） |
| M6 | 消息与微信 | ✅（M6a 微信登录：pc 扫码、小程序静默与手机号快捷登录、公众号 H5 授权与防伪绑定，渠道配置页，按端 appid 支付；M6b 消息体系：模板与日志、站内信/短信/公众号/小程序四通道、异步投递与分类重试、慢队列进程组；M6c 公众号服务器接入、自定义菜单代理、自动回复） |
| M7 | 内容与装修 | ✅（M7a 内容、M7b 地区/版本/导入、M7c DIY 装修 + 移动端配置） |
| M8 | 安装与发布 | ✅（安装向导 + yd:update + Docker compose + 发布包） |
