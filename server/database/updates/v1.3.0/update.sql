-- ============================================================
-- v1.3.0 升级脚本
-- 新增：开放平台配置初始数据
-- 适用于从 v1.2.1 升级
-- ============================================================

-- =============================================
-- 1. 新增开放平台配置（wechat_open 组）
-- =============================================
INSERT IGNORE INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('wechat_open_app_id', '', 'wechat_open', 'string', 'AppID', '微信开放平台网站应用AppID', NULL, NULL, 1, 1, NOW(), NOW()),
('wechat_open_app_secret', '', 'wechat_open', 'string', 'AppSecret', '微信开放平台网站应用AppSecret', NULL, NULL, 2, 1, NOW(), NOW());

-- =============================================
-- 2. 执行完毕后需清除应用缓存
--    php think clear
-- =============================================
