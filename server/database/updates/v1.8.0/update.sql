-- v1.8.0：装修模块（页面装修 / 自定义页面 / 底部导航 / 主题风格 / 链接管理）
-- 表名写裸名即可，php think yd:update 会自动套用表前缀。

-- =============================================
-- 1. 装修相关表
-- =============================================
CREATE TABLE IF NOT EXISTS `diy_pages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page_type` varchar(32) NOT NULL DEFAULT 'home' COMMENT '页面类型:home/member/custom',
  `page_key` varchar(64) NOT NULL DEFAULT '' COMMENT '页面标识(slug);home固定home',
  `platform` varchar(16) NOT NULL DEFAULT 'uniapp' COMMENT '端:uniapp/pc',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '页面名称',
  `components_draft` json DEFAULT NULL COMMENT '草稿组件树',
  `components_published` json DEFAULT NULL COMMENT '已发布组件树',
  `page_settings` json DEFAULT NULL COMMENT '页面设置',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pagekey_platform` (`page_key`,`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面表';

CREATE TABLE IF NOT EXISTS `diy_page_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'diy_pages.id',
  `version_no` int(11) NOT NULL DEFAULT '1' COMMENT '版本号(按page递增)',
  `components` json DEFAULT NULL COMMENT '组件树快照',
  `page_settings` json DEFAULT NULL COMMENT '页面设置快照',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` bigint(20) DEFAULT NULL COMMENT '创建人admin_id',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_page_version` (`page_id`,`version_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面版本快照表';

CREATE TABLE IF NOT EXISTS `diy_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(64) NOT NULL DEFAULT '' COMMENT '链接名称',
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '链接路径或外链',
  `category` varchar(32) NOT NULL DEFAULT '我的链接' COMMENT '分类',
  `icon` varchar(64) DEFAULT NULL COMMENT '图标',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修链接库';

CREATE TABLE IF NOT EXISTS `mobile_configs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `app_name` varchar(100) NOT NULL DEFAULT '' COMMENT '应用名',
  `app_logo` varchar(500) NOT NULL DEFAULT '' COMMENT '应用 Logo',
  `theme_color` varchar(16) NOT NULL DEFAULT '' COMMENT '主题色=主色',
  `theme_colors` json DEFAULT NULL COMMENT '主题色板 {primary,dark,price,page_bg,button_text,badge}',
  `home_app_code` varchar(80) NOT NULL DEFAULT '' COMMENT '启动首页所属应用/内置 code',
  `home_page` varchar(200) NOT NULL DEFAULT '' COMMENT '启动首页路径（空则用装修首页）',
  `tabbar_json` json DEFAULT NULL COMMENT 'tabBar 配置',
  `tabbar_style` json DEFAULT NULL COMMENT 'tabBar 样式 {text_color,active_color,bg_color}',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='移动端配置（主题/tabBar）';

-- =============================================
-- 2. 权限点（幂等）
-- =============================================
INSERT IGNORE INTO `permissions` (`id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (170, 'diy.home.view', '页面装修查看', '装修', '查看页面装修', 'admin', 1, 170, NOW(), NOW()),
  (171, 'diy.home.save', '页面装修保存', '装修', '保存页面装修草稿', 'admin', 1, 171, NOW(), NOW()),
  (172, 'diy.home.publish', '页面装修发布', '装修', '发布页面装修', 'admin', 1, 172, NOW(), NOW()),
  (173, 'diy.home.version.view', '装修版本列表', '装修', '查看装修版本', 'admin', 1, 173, NOW(), NOW()),
  (174, 'diy.home.version.restore', '装修版本回滚', '装修', '回滚装修版本', 'admin', 1, 174, NOW(), NOW()),
  (175, 'diy.page.view', '自定义页面查看', '装修', '查看自定义页面', 'admin', 1, 175, NOW(), NOW()),
  (176, 'diy.page.create', '自定义页面创建', '装修', '创建自定义页面', 'admin', 1, 176, NOW(), NOW()),
  (177, 'diy.page.update', '自定义页面编辑', '装修', '编辑自定义页面', 'admin', 1, 177, NOW(), NOW()),
  (178, 'diy.page.delete', '自定义页面删除', '装修', '删除自定义页面', 'admin', 1, 178, NOW(), NOW()),
  (179, 'diy.page.save', '自定义页面保存', '装修', '保存自定义页面草稿', 'admin', 1, 179, NOW(), NOW()),
  (180, 'diy.page.publish', '自定义页面发布', '装修', '发布自定义页面', 'admin', 1, 180, NOW(), NOW()),
  (181, 'diy.link.list', '链接管理列表', '装修', '查看链接库', 'admin', 1, 181, NOW(), NOW()),
  (182, 'diy.link.create', '链接管理新增', '装修', '新增链接', 'admin', 1, 182, NOW(), NOW()),
  (183, 'diy.link.update', '链接管理编辑', '装修', '编辑链接', 'admin', 1, 183, NOW(), NOW()),
  (184, 'diy.link.delete', '链接管理删除', '装修', '删除链接', 'admin', 1, 184, NOW(), NOW()),
  (185, 'mobile.config.view', '移动端配置查看', '装修', '查看主题/底部导航配置', 'admin', 1, 185, NOW(), NOW()),
  (186, 'mobile.config.update', '移动端配置保存', '装修', '保存主题/底部导航配置', 'admin', 1, 186, NOW(), NOW());

-- =============================================
-- 3. 菜单（幂等）
-- =============================================
INSERT IGNORE INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (16, 0, 1, '装修', 'Diy', '/diy', 'LAYOUT', '/diy/home', 'i-svg:paint-roller', 'diy.home.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 700, NOW(), NOW()),
  (1600, 16, 2, '页面装修', 'DiyHome', '/diy/home', 'diy/decorate-list', NULL, 'i-svg:house', 'diy.home.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (1605, 1600, 3, '保存', NULL, NULL, NULL, NULL, NULL, 'diy.home.save', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (1606, 1600, 3, '发布', NULL, NULL, NULL, NULL, NULL, 'diy.home.publish', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (1607, 1600, 3, '版本列表', NULL, NULL, NULL, NULL, NULL, 'diy.home.version.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (1608, 1600, 3, '回滚版本', NULL, NULL, NULL, NULL, NULL, 'diy.home.version.restore', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (1601, 16, 2, '自定义页面', 'DiyPages', '/diy/pages', 'diy/pages', NULL, 'i-svg:layout-list', 'diy.page.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (1609, 1601, 3, '创建', NULL, NULL, NULL, NULL, NULL, 'diy.page.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (1610, 1601, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'diy.page.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (1611, 1601, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'diy.page.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (1612, 1601, 3, '保存', NULL, NULL, NULL, NULL, NULL, 'diy.page.save', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (1613, 1601, 3, '发布', NULL, NULL, NULL, NULL, NULL, 'diy.page.publish', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (1602, 16, 2, '底部导航', 'DiyTabbar', '/diy/tabbar', 'diy/tabbar', NULL, 'i-svg:layout-list', 'mobile.config.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (1614, 1602, 3, '保存', NULL, NULL, NULL, NULL, NULL, 'mobile.config.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (1603, 16, 2, '主题风格', 'DiyTheme', '/diy/theme', 'diy/theme', NULL, 'i-svg:palette', 'mobile.config.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (1615, 1603, 3, '保存', NULL, NULL, NULL, NULL, NULL, 'mobile.config.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (1604, 16, 2, '链接管理', 'DiyLinks', '/diy/links', 'diy/links', NULL, 'i-svg:link', 'diy.link.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (1616, 1604, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'diy.link.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (1617, 1604, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'diy.link.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (1618, 1604, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'diy.link.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());

-- =============================================
-- 4. 授予超级管理员
-- =============================================
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 1, id, NOW(), NOW() FROM `permissions` WHERE id BETWEEN 170 AND 186;

INSERT IGNORE INTO `role_menus` (`role_id`, `menu_id`, `created_at`, `updated_at`) VALUES
  (1, 16, NOW(), NOW()),
  (1, 1600, NOW(), NOW()),
  (1, 1601, NOW(), NOW()),
  (1, 1602, NOW(), NOW()),
  (1, 1603, NOW(), NOW()),
  (1, 1604, NOW(), NOW()),
  (1, 1605, NOW(), NOW()),
  (1, 1606, NOW(), NOW()),
  (1, 1607, NOW(), NOW()),
  (1, 1608, NOW(), NOW()),
  (1, 1609, NOW(), NOW()),
  (1, 1610, NOW(), NOW()),
  (1, 1611, NOW(), NOW()),
  (1, 1612, NOW(), NOW()),
  (1, 1613, NOW(), NOW()),
  (1, 1614, NOW(), NOW()),
  (1, 1615, NOW(), NOW()),
  (1, 1616, NOW(), NOW()),
  (1, 1617, NOW(), NOW()),
  (1, 1618, NOW(), NOW());
