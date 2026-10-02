# Changelog

本文件按里程碑记录元点Admin 2.x（webman）已交付能力，不从 git 自动生成。2.x 与 1.x（ThinkPHP）数据库不兼容。

## [M8]

安装向导（`GET /install/` 与 JSON 步骤）和 CLI `php webman install` 共用 `core/install/Installer`：空库灌 `schema.sql` / `init.sql` / `regions.sql`，写 `.env` 与 JWT，建超管，打标 `system_upgrades` 2.0.0 并写 `runtime/install.lock`。已有表则拒绝，不 `DROP`。`php webman yd:update`（`--dry-run` / `--baseline`）按 `database/updates/vX.Y.Z/` 升级；M8 无待跑目录，已有库首次用 `--baseline=2.0.0` 只打标。

Docker：仓库根 `docker/` 提供 nginx + webman（PHP 8.4 cli，反代 HTTP 8000 与 `/ws` → 8001）+ 空 MySQL 8 + Redis 7。`docker compose up` 后走 `/install/` 或 `docker compose exec webman php webman install`。不灌 init SQL，不挂宿主机开发库。

发布：`server/scripts/release.sh` 构建 admin（`pnpm build`）与 pc（`pnpm generate`），打 `dist/ydadmin-2.0.0.zip`（`ydadmin-2.0.0/server/...`），不含 `vendor/`、`.env`、前端源码、`docker/`、`tests/`。用户解压后 `composer install` 再走向导。

## [M7]

内容（文章/栏目/公告/协议/反馈）与地区、App 版本、数据导入；DIY 装修（草稿/发布/版本、15 个内置组件）与移动端主题/底部导航。C 端只出已发布页。

## [M6]

微信登录（PC 扫码、小程序、公众号 H5）；消息模板与站内信/短信/公众号/小程序四通道、异步投递与慢队列；公众号服务器接入、自定义菜单代理、自动回复。

## [M5]

C 端认证与短信验证码、余额与积分；微信支付 v3 与支付宝充值、回调验签、同事务入账、超时关单、命令行退款与对账。

## [M4]

WebSocket 进程 + 一次性票据握手；通知实时推送、在线管理员、强制下线；被吊销会话断开。

## [M3]

scheduler 按 cron 投递白名单命令；redis-queue 消费；操作日志异步落库；`failed_jobs` 与重试命令。

## [M2]

按表生成 CRUD（PHP + Vue/TS）与 `make:crud`；由路由与校验规则推导 OpenAPI。

## [M1]

认证、RBAC、数据权限；管理员/角色/菜单/部门；系统配置、字典、日志、站内通知、仪表盘、素材上传。

## [M0]

webman 骨架：`{code,message,data,timestamp}`、admin/user 双 JWT、默认拒绝的权限、测试库 bootstrap 与五道门禁。
