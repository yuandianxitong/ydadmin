# v1.6.0 升级说明

## 前置条件

MySQL 8.0+（`utf8mb4_0900_ai_ci` 排序规则依赖 MySQL 8.0）。

## 变更内容

### 修正 failed_jobs 表排序规则漂移

v1.5.3 已将全部表的排序规则统一为 `utf8mb4_0900_ai_ci`，但 `failed_jobs` 表（v1.4.0 新增）当时未包含在转换清单中，仍为 `utf8mb4_unicode_ci`，导致老实例与全新安装（`schema.sql` 已是 `0900_ai_ci`）之间存在排序规则漂移。本版本补齐该表的转换。

> 仅执行过 v1.4.0 + v1.5.3 升级的老实例存在此漂移；v1.6.0 之后新安装的实例不受影响。

## 升级步骤

```bash
# 1. 备份数据库
# 2. 拉取新版本代码
cd server
php think yd:update        # 自动处理表前缀
```

`CONVERT TO ...` 可安全重复执行；若中途失败，修复后重跑 `php think yd:update` 会从本版本继续。
