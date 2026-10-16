# 2.0.9：配置持久化版本

新增 `configuration_state`，配置变更与版本在同一事务提交；Redis 缓存按数据库版本隔离。
SQL 可以重复执行，`INSERT IGNORE` 不重置已有版本。

升级前备份，执行 `php webman yd:update --dry-run`、`php webman yd:update`，再重启全部进程。
新安装 schema 已包含此表。本次开发测试不升级业务库。
