CREATE TABLE IF NOT EXISTS `plugins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(100) NOT NULL,
  `version` varchar(20) NOT NULL,
  `author` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  `icon` varchar(255) DEFAULT NULL,
  `kind` varchar(16) NOT NULL DEFAULT 'plugin',
  `depends` json DEFAULT NULL,
  `read_safe_routes` json DEFAULT NULL,
  `package_path` varchar(255) NOT NULL DEFAULT '',
  `package_hash` char(64) NOT NULL DEFAULT '',
  `manifest` json NOT NULL,
  `status` tinyint NOT NULL DEFAULT 3 COMMENT '1=已启用 2=已禁用 3=安装中 4=升级中 5=安装失败 6=已卸载',
  `last_error` varchar(1000) NOT NULL DEFAULT '',
  `installed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='插件主表';

CREATE TABLE IF NOT EXISTS `plugin_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` int unsigned NOT NULL,
  `plugin_code` varchar(64) NOT NULL,
  `migration_name` varchar(191) NOT NULL,
  `file_hash` char(64) NOT NULL,
  `direction` enum('up','down') NOT NULL,
  `plugin_version` varchar(32) DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_msg` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plugin_migration_dir_status` (`plugin_id`,`migration_name`,`direction`,`status`),
  KEY `idx_plugin_code` (`plugin_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='插件 migration 执行状态';

CREATE TABLE IF NOT EXISTS `plugin_sagas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` int unsigned NOT NULL,
  `code` varchar(80) NOT NULL,
  `operation` varchar(16) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `current_step` varchar(64) NOT NULL DEFAULT '',
  `completed_steps` text,
  `step_state` text,
  `payload` text,
  `expected_fingerprint` varchar(64) NOT NULL DEFAULT '',
  `observed_fingerprint` varchar(64) NOT NULL DEFAULT '',
  `claimed_pid` int unsigned NOT NULL DEFAULT 0,
  `claimed_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `error` varchar(1000) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_id` (`status`, `id`),
  KEY `idx_plugin_status` (`plugin_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='插件 saga 状态';

CREATE TABLE IF NOT EXISTS `plugin_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_code` varchar(64) NOT NULL COMMENT '插件编码',
  `menu_id` int unsigned NOT NULL COMMENT '本插件插入的菜单ID（menus.id）',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_menu` (`plugin_code`,`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='插件菜单归属（停用和卸载时据此找到菜单）';
