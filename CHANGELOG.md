# Changelog

本文件按里程碑记录元点Admin 2.x（webman）已交付能力，不从 git 自动生成。2.x 与 1.x（ThinkPHP）数据库不兼容。

## [未发布]

- Docker 生成器挂载前端源码并在写入前检查挂载，避免生成文件只留在容器内。
- 修复生成代码的空日期、选填文本、可空字段清空、日期格式及列表 TypeScript 错误；状态筛选使用下拉框，时间字段补充中文标签。
- 生成器支持配置下拉选项，缺失选项时拒绝生成；冲突返回 409 业务码并展示文件结果。
- 禁止生成到核心模块；密码自动哈希并隐藏，敏感字段不进入响应和列表。

- API 文档在生产模式下也可用，两条端点统一要求管理端登录及 `system.api_doc` 权限；新窗口与 JSON 下载支持认证。
- 页面设计器允许空白草稿，首次保存前保留初始快照；历史版本操作明确为载入草稿，需重新发布生效。
- 实时站内通知增加弹窗提示，继续同步铃铛未读数。

- 修复 Docker 重复安装 mbstring、验证码缺 GD、大型静态资源代理连接及日志客户端 IP 配置。
- 图片上传校验真实 MIME、图片结构与扩展名；普通文件端点同样校验图片内容。
- 修复文章抽屉关闭时重置编辑器的问题；补充分类必填校验，新增成功后返回列表第一页。
- 部门电话增加前后端格式校验；图片上传成功后收起编辑器菜单；修复暗色模式自定义主色覆盖。
- PC 公告链接增加详情页；补齐容器内 Composer 安装、连接参数及生产模式生成器限制说明。

## [2.0.9]

移除独立模块描述、模块清单接口与检查命令；CRUD 生成和增量合并继续使用生成基线。

系统配置与持久化版本同事务提交，短信、存储、支付、邮件按操作使用配置快照，短信供应商和凭据变更无需重启；动态校验文档按配置版本重建。

生成器记录生成基线和选项，支持增量预览、三方合并、冲突保护、备份及旧模块原始快照导入；`--force` 改为先备份再替换。

已有数据库需升级到 2.0.9 并重启进程。

## [2.0.8]

业务消息、定时调度和文件任务使用数据库 outbox 持久化投递，支持去重、租约隔离、重试与上下文传递。操作日志、定时命令、消息和文件任务分别消费。

余额、积分调整及任务提交支持 `Idempotency-Key`。新增异步会员 CSV 导入、三类数据导出、进度查询、取消与私有下载；执行时重新检查权限，导入支持检查点恢复。

新增就绪探针、超级管理员运行监控、慢请求与慢查询日志、到期数据清理。已有数据库需升级到 2.0.8 并重启所有后台进程。

## [M8]

安装向导（`GET /install/` 与 JSON 步骤）和 CLI `php webman install` 共用 `core/install/Installer`：空库灌 `schema.sql` / `init.sql` / `regions.sql`，写 `.env` 与 JWT，建超管，打标 `system_upgrades` 2.0.0 并写 `config/install.lock`。已有表则拒绝，不 `DROP`。`php webman yd:update`（`--dry-run` / `--baseline`）按 `database/updates/vX.Y.Z/` 升级；M8 无待跑目录，已有库首次用 `--baseline=2.0.0` 只打标。

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
