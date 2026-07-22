# 数据库升级指南

本目录存放每个版本的增量数据库升级脚本，供已部署实例从旧版本升级到新版本使用。

框架发行版升级的**唯一数据库机制**是 `php think yd:update`（不再使用 think-migration）。
升级器会自动处理**数据表前缀**，并把已应用版本记录在 `system_upgrades` 表中，保证幂等、可断点续跑。

## 目录结构

```
updates/
├── README.md            # 本文件
└── vX.Y.Z/
    ├── update.sql       # 增量 SQL（ALTER TABLE、CREATE TABLE、INSERT/UPDATE 等），可选
    └── update.php       # 需要 PHP 逻辑的数据迁移钩子，可选
```

- 目录名必须是 `vX.Y.Z`（语义化版本），与 `CHANGELOG.md` / `config/version.php` 对应。
- 没有数据库变更的版本不需要创建目录。
- 一个版本目录可同时包含 `update.sql` 与 `update.php`（先执行 SQL，再执行 PHP 钩子）。

## 升级流程（推荐：命令行）

```bash
# 1. 备份数据库（务必！）
# 2. 拉取新版本框架代码（git pull / 覆盖文件）
cd server

# 3. 预览将执行哪些版本，不实际执行
php think yd:update --dry-run

# 4. 正式执行升级
php think yd:update
```

升级器会：

1. 读取 `system_upgrades` 表中已应用的版本；
2. 扫描本目录所有 `vX.Y.Z`，筛出**未应用**的版本并按语义化版本排序；
3. 依次执行 `update.sql`（自动套用当前表前缀）与 `update.php`；
4. 每成功一个版本写入 `system_upgrades`；
5. 全部完成后清理 runtime 缓存。

中途失败会立即中断并提示，修复后重跑 `php think yd:update` 会**从失败版本继续**，已成功的版本不会重复执行。

## 首次使用 / 老用户升级基线

`system_upgrades` 表是判断"已升级到哪一版"的事实来源。

- **全新安装**：安装程序会自动把当前所有历史版本记为"已应用"，无需任何操作。
- **老用户首次使用升级系统**（数据库里还没有 `system_upgrades` 记录）：升级器无法自动判断你当前的数据库版本，需要用 `--baseline` 指定：

```bash
# 告诉升级器"我的库当前已是 1.5.3"，<= 1.5.3 的脚本全部记为已应用，只执行之后的版本
php think yd:update --baseline=1.5.3

# 若是从头搭建的空库、需要执行全部历史脚本
php think yd:update --baseline=0
```

`--baseline` 只在首次（无任何升级记录）时需要，之后的升级全自动。

## 表前缀

**升级脚本一律书写裸表名**（如 `admins`、`users`），不要手写前缀。执行时升级器会用与安装程序完全一致的逻辑（`core/database/SqlRunner`）自动套上当前 `DB_PREFIX`。因此无论用户是否设置了表前缀，同一份脚本都能正确执行。

## 编写规范（框架作者）

- SQL 使用**裸表名**，禁止硬编码前缀。
- 语句必须**幂等 / 可重入**：新增表用 `CREATE TABLE IF NOT EXISTS`，新增列/索引前先判断是否存在（MySQL 的 DDL 会自动提交，失败重跑时前面的语句可能已生效）。
- `update.php` 钩子需返回一个 `callable(\PDO $pdo, string $prefix): void`，例如：

```php
<?php
// database/updates/v1.6.0/update.php
return function (PDO $pdo, string $prefix) {
    // 需要 PHP 逻辑的数据迁移（如按已用 ID 动态插入菜单）
    $table = '`' . $prefix . 'system_configs`';
    $pdo->exec("UPDATE {$table} SET `config_group` = 'basic' WHERE `config_group` = ''");
};
```

- 每次发版若涉及数据库变更，需同时更新：
  1. `server/public/install/data/schema.sql`（保证新安装拿到最新结构）
  2. 本目录 `vX.Y.Z/update.sql`（保证老用户可升级）
  3. `server/config/version.php` 版本号
  4. `CHANGELOG.md`
