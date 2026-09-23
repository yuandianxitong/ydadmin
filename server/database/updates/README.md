# 升级目录写法

一个版本一个目录（`v2.0.1`、`v2.0.2`…），里面放 `update.sql` 和/或 `update.php`。
`php webman yd:update` 按 `version_compare` 升序执行尚未记录在 `system_upgrades` 里的版本。

几条硬性要求：

- **必须可重跑**。执行 SQL 与写 `system_upgrades` 不在同一个事务里（MySQL 的 DDL 本来就会隐式提交），
  一个版本执行到一半失败，重跑会从这个版本的**第一条语句**重新来过。所以：
  建表用 `CREATE TABLE IF NOT EXISTS`，插种子用 `INSERT IGNORE`（或 `INSERT ... WHERE NOT EXISTS`），
  加列先查 `information_schema.COLUMNS`，加索引先查 `information_schema.STATISTICS`。
- **不要动管理员可能改过的数据**。回填只填空值（`WHERE col IS NULL`），
  修种子只按原值匹配替换，不要无条件 `UPDATE`。
- **删数据要想清楚**。删之前确认没有别的表引用它，并在文件头写明为什么可以删。
- 新增目录后记得把 `config/version.php` 的 `version` 提到 ≥ 该目录版本，
  否则新装会挂着一个永远待应用的升级（`SchemaTest` 会拦这个）。
- 表结构变化同时要改 `database/install/schema.sql`（新装走它）和 `core/database/DevDatabaseGuard`
  的 `REQUIRED`（开发库过期时才会提示重建）。
