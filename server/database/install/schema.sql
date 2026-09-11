-- 元点Admin 表结构（全新安装的完整状态）
-- 按里程碑追加：每个里程碑只加本里程碑用到的表（spec §6）。时间字段统一 datetime。

-- ---------------------------------------------------------------- M1a 系统核心

CREATE TABLE `admins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `password` varchar(255) NOT NULL COMMENT '密码（password_hash）',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `department_id` int unsigned DEFAULT NULL COMMENT '所属部门',
  `position` varchar(100) DEFAULT NULL COMMENT '职位',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int unsigned NOT NULL DEFAULT 0 COMMENT '登录次数',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态:1启用 0禁用',
  `created_by` int unsigned DEFAULT NULL COMMENT '创建人',
  `updated_by` int unsigned DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_department` (`department_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员';

CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '角色标识',
  `title` varchar(100) NOT NULL COMMENT '角色名称',
  `description` varchar(500) DEFAULT NULL COMMENT '描述',
  `data_scope` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '数据范围:1全部 2本部门 3本部门及下级 4仅本人 5自定义',
  `is_system` tinyint NOT NULL DEFAULT 0 COMMENT '系统角色:1是 0否（拥有者即超级管理员）',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态:1启用 0禁用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='角色';

CREATE TABLE `admin_roles` (
  `admin_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`admin_id`, `role_id`),
  KEY `idx_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员-角色';

CREATE TABLE `menus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT 0 COMMENT '父级ID',
  `type` tinyint NOT NULL COMMENT '类型:1目录 2菜单 3按钮',
  `title` varchar(100) NOT NULL COMMENT '标题',
  `name` varchar(100) DEFAULT NULL COMMENT '路由名称',
  `path` varchar(200) DEFAULT NULL COMMENT '路由路径',
  `component` varchar(255) DEFAULT NULL COMMENT '组件路径',
  `redirect` varchar(200) DEFAULT NULL COMMENT '重定向',
  `icon` varchar(100) DEFAULT NULL COMMENT '图标',
  `permission` varchar(100) DEFAULT NULL COMMENT '权限点（前端 v-has-perm 与后端 #[Permission] 共用）',
  `is_hidden` tinyint NOT NULL DEFAULT 0,
  `is_cache` tinyint NOT NULL DEFAULT 1,
  `is_affix` tinyint NOT NULL DEFAULT 0,
  `is_iframe` tinyint NOT NULL DEFAULT 0,
  `external_link` varchar(255) DEFAULT NULL,
  `breadcrumb` tinyint NOT NULL DEFAULT 1,
  `active_menu` varchar(200) DEFAULT NULL,
  `meta` json DEFAULT NULL COMMENT '覆盖派生 meta 的字段',
  `status` tinyint NOT NULL DEFAULT 1,
  `sort` int NOT NULL DEFAULT 0,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_permission` (`permission`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='菜单与按钮';

CREATE TABLE `role_menus` (
  `role_id` int unsigned NOT NULL,
  `menu_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`role_id`, `menu_id`),
  KEY `idx_menu` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='角色-菜单';

CREATE TABLE `departments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT 0 COMMENT '上级部门',
  `name` varchar(100) NOT NULL COMMENT '部门名称',
  `code` varchar(50) DEFAULT NULL COMMENT '部门编码',
  `leader` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  `sort` int NOT NULL DEFAULT 0,
  `remark` varchar(255) DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='部门';

CREATE TABLE `role_departments` (
  `role_id` int unsigned NOT NULL,
  `department_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`role_id`, `department_id`),
  KEY `idx_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='角色自定义数据范围（data_scope=5）';

CREATE TABLE `system_configs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL COMMENT '配置键',
  `config_value` text COMMENT '配置值（字符串，按 config_type 转换）',
  `config_group` varchar(50) NOT NULL DEFAULT 'basic' COMMENT '分组',
  `config_type` varchar(20) NOT NULL DEFAULT 'string' COMMENT 'string|number|boolean|json|select|file',
  `config_name` varchar(100) DEFAULT NULL COMMENT '显示名',
  `config_desc` varchar(255) DEFAULT NULL COMMENT '说明',
  `config_options` json DEFAULT NULL COMMENT 'select 选项',
  `config_depends` json DEFAULT NULL COMMENT '前端联动显示条件',
  `sort_order` int NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`config_key`),
  KEY `idx_group_sort` (`config_group`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='系统配置';

CREATE TABLE `admin_login_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '管理员ID（用户名不存在时为 0）',
  `username` varchar(50) NOT NULL DEFAULT '' COMMENT '登录时提交的用户名',
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `login_time` datetime DEFAULT NULL,
  `login_result` tinyint NOT NULL DEFAULT 0 COMMENT '1成功 0失败',
  `login_message` varchar(255) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_username` (`username`),
  KEY `idx_login_time` (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员登录日志';
