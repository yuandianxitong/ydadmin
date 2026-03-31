# v1.3.0 升级说明

## 升级内容

1. **新增开放平台配置** — `system_configs` 表新增 `wechat_open` 组的 `wechat_open_app_id` 和 `wechat_open_app_secret` 两条初始配置记录

## 升级步骤

1. 备份数据库
2. 执行 `update.sql`
3. 运行 `php think clear` 清除缓存
4. 将 `server/config/version.php` 中的版本号更新为 `1.3.0`
