# v1.4.0 数据库升级说明

## 升级步骤

1. 备份当前数据库
2. 执行 `update.sql`

```bash
mysql -u your_user -p your_database < update.sql
```

## 变更内容

### 新增表

- **`failed_jobs`**：Redis 队列失败任务记录表，用于存储异步任务执行失败时的异常信息，便于排查和重试。

### 索引变更

- **`admin_login_logs`**：新增 `(admin_id, login_time)` 复合索引（`idx_admin_id_login_time`），优化按管理员 ID 查询登录记录的性能。

## 注意事项

- `failed_jobs` 表使用 `CREATE TABLE IF NOT EXISTS`，重复执行不会报错。
- `admin_login_logs` 新增索引前，请确认该索引不存在，避免重复添加。
