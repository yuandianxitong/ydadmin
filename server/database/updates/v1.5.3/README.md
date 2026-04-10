# v1.5.3 升级说明

## 前置条件

**必须先升级至 MySQL 8.0+**，否则 `utf8mb4_0900_ai_ci` 排序规则不可用，脚本执行将报错。

## 变更内容

### 数据库字符集排序规则升级

将全部 36 张表的排序规则从 `utf8mb4_unicode_ci` 升级为 `utf8mb4_0900_ai_ci`。

`utf8mb4_0900_ai_ci` 是 MySQL 8.0 的默认排序规则，相较于 `utf8mb4_unicode_ci` 有以下优势：

- 基于 Unicode 9.0 标准，字符覆盖更完整
- 排序性能更高（原生 8.0 实现，无需额外转换）
- 与 MySQL 8.0 新建数据库的默认行为保持一致，减少混用风险

## 升级步骤

1. 确认 MySQL 版本 >= 8.0：`SELECT VERSION();`
2. 备份数据库
3. 执行升级脚本：

```bash
mysql -u root -p your_database < server/database/updates/v1.5.3/update.sql
```

如果数据库使用了表前缀（如 `yd_`），需要在执行前将脚本中所有表名替换为带前缀的完整表名，例如将 `admins` 替换为 `yd_admins`。

## 涉及的表

admins, roles, permissions, menus, admin_roles, role_permissions, role_menus,
admin_login_logs, admin_operation_logs, system_configs, departments, dictionaries,
dictionary_items, files, notifications, notification_reads, cron_jobs, cron_job_logs,
payment_orders, message_templates, message_logs, wechat_auto_replies, users,
agreements, announcements, feedbacks, regions, app_versions, data_imports,
user_notifications, user_notification_reads, article_categories, articles,
balance_logs, points_logs, failed_jobs
