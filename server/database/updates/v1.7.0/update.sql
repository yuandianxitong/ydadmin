-- v1.7.0：AI 编译 apply 门禁 + Artifact 状态机
-- 表名写裸名即可，php think yd:update 会自动套用表前缀。
-- 前置条件：无（不依赖 MySQL 8.0+ 新特性以外的能力）。

-- =============================================
-- 1. 新增 ai_artifacts 表（AI 编译工件状态机）
-- =============================================
CREATE TABLE IF NOT EXISTS `ai_artifacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spec_id` varchar(32) NOT NULL COMMENT '来源 spec_id',
  `stage_id` varchar(32) NOT NULL COMMENT '编译 stage 目录名',
  `module` varchar(64) NOT NULL COMMENT '模块名',
  `title` varchar(128) DEFAULT NULL COMMENT '模块标题',
  `state` varchar(20) NOT NULL COMMENT '状态:compiled,checking,checked_passed,checked_failed,applied,superseded',
  `check_summary` json DEFAULT NULL COMMENT '最近检查结果摘要',
  `checked_at` timestamp NULL DEFAULT NULL COMMENT '最近检查时间',
  `applied_at` timestamp NULL DEFAULT NULL COMMENT 'apply 成功时间',
  `applied_files` json DEFAULT NULL COMMENT 'apply 写入文件清单',
  `error` varchar(500) DEFAULT NULL COMMENT '失败原因摘要',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_artifacts_spec_id_index` (`spec_id`),
  KEY `ai_artifacts_state_index` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='AI 编译工件';

-- =============================================
-- 2. 补齐权限点（幂等）
-- =============================================
INSERT IGNORE INTO `permissions` (`id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (164, 'ai.ydspec.use', 'AI 建模向导', '开发工具', 'AI 建模向导页面访问、编译、检查权限', 'admin', 1, 164, NOW(), NOW()),
  (165, 'ai.ydspec.apply', '应用编译结果', '开发工具', '将 YdSpec 编译结果经门禁写入项目的权限', 'admin', 1, 165, NOW(), NOW());

-- =============================================
-- 3. 补齐「AI 建模向导」菜单及其「应用」按钮菜单（幂等）
--    注：菜单 222（AI 建模向导，权限 ai.ydspec.use）随子项目2（编译链路）引入，
--    与本次子项目3（apply 门禁）的菜单 223 一样，均未随 v1.6.0 发行，此处一并补齐，
--    避免菜单 223 的 parent_id 悬空指向不存在的父级。
-- =============================================
INSERT IGNORE INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (222, 3, 2, 'AI 建模向导', 'DevYdSpec', '/system/ydspec', 'system/ydspec/index', NULL, 'i-svg:boxes', 'ai.ydspec.use', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (223, 222, 3, '应用', NULL, NULL, NULL, NULL, NULL, 'ai.ydspec.apply', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW());

-- =============================================
-- 4. 授予超级管理员角色新增菜单（幂等）
-- =============================================
INSERT IGNORE INTO `role_menus` (`role_id`, `menu_id`, `created_at`, `updated_at`) VALUES
  (1, 222, NOW(), NOW()),
  (1, 223, NOW(), NOW());
