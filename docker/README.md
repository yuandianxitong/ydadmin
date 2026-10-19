# Docker 部署

适合本机或一台已装 Docker 的服务器，一次拉起 nginx、webman、MySQL、Redis。

## 准备代码与依赖

```bash
git clone https://github.com/yuandianxitong/ydadmin.git
cd ydadmin/docker
cp .env.example .env
```

编辑 `docker/.env`，填写 `MYSQL_ROOT_PASSWORD`、`MYSQL_PASSWORD`、`REDIS_PASSWORD`。这三个没有默认值，不填 `docker compose` 会直接拒绝启动。可用下面命令各生成一串随机值：

```bash
docker run --rm php:8.4-cli-bookworm php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

## 启动

```bash
docker compose build webman
docker compose run --rm --no-deps --entrypoint composer webman install --no-dev --optimize-autoloader
docker compose up -d
```

Composer 在 Webman 镜像内运行，宿主机无需安装 PHP 8.4 或 Composer；`--no-deps` 避免安装依赖时提前启动数据库等服务。上述命令均在 `docker/` 中执行。

浏览器打开 `http://localhost/install/`，按向导完成安装，连接信息如下：

| 安装项 | 填写值 |
|---|---|
| 数据库主机 / 端口 | `mysql` / `3306` |
| 数据库名 | `docker/.env` 的 `MYSQL_DATABASE`，默认 `ydadmin`，向导会预填此值 |
| 数据库用户 / 密码 | `MYSQL_USER`（默认 `ydadmin`）/ `MYSQL_PASSWORD` |
| Redis 主机 / 端口 | `redis` / `6379` |
| Redis 密码 / DB | `REDIS_PASSWORD` / `0` |

向导使用容器网络地址与端口，与宿主机映射的 `MYSQL_PORT` / `REDIS_PORT` 无关。`docker/.env` 用于 Compose，安装器写入的应用配置在 `server/.env`，安装锁为 `server/config/install.lock`。

装完执行（不要在宿主机运行 `php start.php restart`）：

```bash
docker compose restart webman
```

管理后台：`http://localhost/admin/`。用户名默认 `admin`，密码在安装时设置。

## 说明

代码生成器需要完整仓库源码。Compose 将宿主机 `server/` 挂载到 `/var/www/html`，将 `admin/` 挂载到 `/var/www/admin`；生成前会检查前端挂载，缺失时整批拒绝写入。

如果此前已有前端代码生成在旧容器内，请先在 `docker/` 目录导出，再更新并重建容器，避免旧文件丢失：

```bash
mkdir -p ../recovered-generator
docker compose cp webman:/var/www/admin/src ../recovered-generator/
docker compose up -d --force-recreate webman
```

核对 `recovered-generator/src` 中的 API 和页面文件后，手工合入宿主机 `admin/src`。不要用旧容器导出的整个目录覆盖现有源码。若旧容器已经删除且没有备份，这些文件只能重新生成。

- nginx 把 `/` 反代到 webman `8000`，把 `/ws` 反代到 `8001`，并设置 `client_max_body_size 50m`。
- MySQL 与 Redis 默认只绑定宿主机回环地址；需要外连时再改 `docker/.env` 里的绑定项，并自行评估暴露面。
- Nginx 的固定容器 IP 默认为 `172.30.84.2`，启动脚本将其写入应用的 `TRUSTED_PROXIES`，登录日志与操作日志由此记录实际客户端 IP。若子网冲突，成对修改 `DOCKER_SUBNET` 与 `NGINX_INTERNAL_IP`，然后 `docker compose up -d --force-recreate` 重建容器。不要把整个私网或所有来源设为可信代理。
- 镜像包含验证码所需 GD（PNG / JPEG / WebP / FreeType）以及上传检测所需 fileinfo。
- 安装器默认设置 `APP_DEBUG=false`，生成器写操作在生产模式下关闭。仅在独立开发环境将 `server/.env` 改为 `APP_DEBUG=true` 并执行 `docker compose restart webman`，详见[代码生成器](../README.md#代码生成)。
