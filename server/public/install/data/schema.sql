-- ============================================================
-- 元点Admin - 数据库结构文件
-- ============================================================

-- 管理员表
CREATE TABLE IF NOT EXISTS `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `email` varchar(100) NOT NULL COMMENT '邮箱',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `department_id` int(10) unsigned DEFAULT NULL COMMENT '部门ID',
  `department` varchar(100) DEFAULT NULL COMMENT '部门（兼容字段）',
  `position` varchar(100) DEFAULT NULL COMMENT '职位',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT '最后登录IP',
  `last_login_time` timestamp NULL DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int(11) NOT NULL DEFAULT '0' COMMENT '登录次数',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1正常,0禁用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT '创建者ID',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT '更新者ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_username_unique` (`username`),
  UNIQUE KEY `admins_email_unique` (`email`),
  KEY `admins_mobile_index` (`mobile`),
  KEY `admins_status_index` (`status`),
  KEY `admins_created_at_index` (`created_at`),
  KEY `idx_department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

-- 角色表
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '角色标识',
  `title` varchar(100) NOT NULL COMMENT '角色名称',
  `description` text COMMENT '角色描述',
  `data_scope` tinyint(1) NOT NULL DEFAULT '1' COMMENT '数据权限:1全部,2自定义,3本部门,4本部门及下级,5仅本人',
  `is_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否系统角色:1是,0否',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1正常,0禁用',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT '创建者ID',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT '更新者ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`),
  KEY `roles_status_index` (`status`),
  KEY `roles_sort_index` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';

-- 权限表
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '权限标识',
  `title` varchar(100) NOT NULL COMMENT '权限名称',
  `group` varchar(50) NOT NULL COMMENT '权限分组',
  `description` text COMMENT '权限描述',
  `guard_name` varchar(50) NOT NULL DEFAULT 'admin' COMMENT '守卫名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  KEY `permissions_group_index` (`group`),
  KEY `permissions_guard_name_index` (`guard_name`),
  KEY `permissions_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限表';

-- 菜单表
CREATE TABLE IF NOT EXISTS `menus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '父级ID',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '菜单类型:1目录,2菜单,3按钮',
  `title` varchar(100) NOT NULL COMMENT '菜单标题',
  `name` varchar(100) DEFAULT NULL COMMENT '路由名称',
  `path` varchar(200) DEFAULT NULL COMMENT '路由地址',
  `component` varchar(255) DEFAULT NULL COMMENT '组件地址',
  `redirect` varchar(200) DEFAULT NULL COMMENT '重定向地址',
  `icon` varchar(100) DEFAULT NULL COMMENT '菜单图标',
  `permission` varchar(100) DEFAULT NULL COMMENT '权限标识',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否隐藏:1是,0否',
  `is_cache` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否缓存:1是,0否',
  `is_affix` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否固定标签:1是,0否',
  `is_iframe` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否内嵌:1是,0否',
  `external_link` varchar(255) DEFAULT NULL COMMENT '外链地址',
  `breadcrumb` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示面包屑:1是,0否',
  `active_menu` varchar(200) DEFAULT NULL COMMENT '高亮菜单',
  `meta` json DEFAULT NULL COMMENT '扩展元数据',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT '创建者ID',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT '更新者ID',
  PRIMARY KEY (`id`),
  KEY `menus_parent_id_index` (`parent_id`),
  KEY `menus_type_index` (`type`),
  KEY `menus_name_index` (`name`),
  KEY `menus_path_index` (`path`),
  KEY `menus_permission_index` (`permission`),
  KEY `menus_status_index` (`status`),
  KEY `menus_sort_index` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='菜单表';

-- 管理员角色关联表
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '管理员ID',
  `role_id` bigint(20) unsigned NOT NULL COMMENT '角色ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`admin_id`,`role_id`),
  KEY `admin_roles_admin_id_index` (`admin_id`),
  KEY `admin_roles_role_id_index` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色关联表';

-- 角色权限关联表
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL COMMENT '角色ID',
  `permission_id` bigint(20) unsigned NOT NULL COMMENT '权限ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permissions_role_id_index` (`role_id`),
  KEY `role_permissions_permission_id_index` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- 角色菜单关联表
CREATE TABLE IF NOT EXISTS `role_menus` (
  `role_id` bigint(20) unsigned NOT NULL COMMENT '角色ID',
  `menu_id` bigint(20) unsigned NOT NULL COMMENT '菜单ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`,`menu_id`),
  KEY `role_menus_role_id_index` (`role_id`),
  KEY `role_menus_menu_id_index` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色菜单关联表';

-- 管理员登录日志表
CREATE TABLE IF NOT EXISTS `admin_login_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '管理员ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `ip` varchar(45) NOT NULL COMMENT '登录IP',
  `user_agent` text COMMENT '用户代理',
  `login_time` timestamp NOT NULL COMMENT '登录时间',
  `login_result` tinyint(1) NOT NULL COMMENT '登录结果:1成功,0失败',
  `login_message` varchar(255) DEFAULT NULL COMMENT '登录消息',
  `browser` varchar(100) DEFAULT NULL COMMENT '浏览器',
  `os` varchar(100) DEFAULT NULL COMMENT '操作系统',
  PRIMARY KEY (`id`),
  KEY `admin_login_logs_admin_id_index` (`admin_id`),
  KEY `admin_login_logs_username_index` (`username`),
  KEY `admin_login_logs_ip_index` (`ip`),
  KEY `admin_login_logs_login_time_index` (`login_time`),
  KEY `admin_login_logs_login_result_index` (`login_result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员登录日志表';

-- 管理员操作日志表
CREATE TABLE IF NOT EXISTS `admin_operation_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '管理员ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `method` varchar(10) NOT NULL COMMENT '请求方法',
  `path` varchar(255) NOT NULL COMMENT '请求路径',
  `ip` varchar(45) NOT NULL COMMENT '操作IP',
  `user_agent` text COMMENT '用户代理',
  `action` varchar(100) NOT NULL COMMENT '操作动作',
  `description` varchar(255) NOT NULL COMMENT '操作描述',
  `params` json DEFAULT NULL COMMENT '请求参数',
  `result` json DEFAULT NULL COMMENT '操作结果',
  `operation_time` timestamp NOT NULL COMMENT '操作时间',
  `execution_time` decimal(8,3) DEFAULT NULL COMMENT '执行时间(秒)',
  PRIMARY KEY (`id`),
  KEY `admin_operation_logs_admin_id_index` (`admin_id`),
  KEY `admin_operation_logs_username_index` (`username`),
  KEY `admin_operation_logs_method_index` (`method`),
  KEY `admin_operation_logs_path_index` (`path`),
  KEY `admin_operation_logs_action_index` (`action`),
  KEY `admin_operation_logs_operation_time_index` (`operation_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作日志表';

-- 系统配置表
CREATE TABLE IF NOT EXISTS `system_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL COMMENT '配置键',
  `config_value` text COMMENT '配置值',
  `config_group` varchar(50) NOT NULL DEFAULT 'basic' COMMENT '配置分组',
  `config_type` varchar(20) NOT NULL DEFAULT 'string' COMMENT '配置类型:string,number,boolean,json,file',
  `config_name` varchar(100) NOT NULL COMMENT '配置名称',
  `config_desc` varchar(255) DEFAULT NULL COMMENT '配置描述',
  `config_options` json DEFAULT NULL COMMENT '下拉选项 {"value":"label",...}',
  `config_depends` json DEFAULT NULL COMMENT '显示依赖 {"field":"xxx","value":"yyy"} 或 {"field":"xxx","value":["a","b"]}',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_configs_key_unique` (`config_key`),
  KEY `system_configs_group_index` (`config_group`),
  KEY `system_configs_status_index` (`status`),
  KEY `system_configs_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ============================================================
-- 部门表
-- ============================================================
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '上级部门ID',
  `name` varchar(100) NOT NULL COMMENT '部门名称',
  `code` varchar(50) DEFAULT NULL COMMENT '部门编码',
  `leader` varchar(50) DEFAULT NULL COMMENT '负责人',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1正常 0禁用',
  `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_by` int(10) unsigned DEFAULT NULL COMMENT '创建人',
  `updated_by` int(10) unsigned DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门表';

-- ============================================================
-- 数据字典表
-- ============================================================
CREATE TABLE IF NOT EXISTS `dictionaries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '字典名称',
  `code` varchar(100) NOT NULL COMMENT '字典编码（唯一标识）',
  `description` varchar(500) DEFAULT '' COMMENT '描述',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态：1启用 0禁用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据字典表';

CREATE TABLE IF NOT EXISTS `dictionary_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dictionary_id` int unsigned NOT NULL COMMENT '字典ID',
  `label` varchar(100) NOT NULL COMMENT '标签（显示文本）',
  `value` varchar(100) NOT NULL COMMENT '值',
  `tag_type` varchar(50) DEFAULT '' COMMENT '标签类型（success/warning/danger/info）',
  `description` varchar(500) DEFAULT '' COMMENT '描述',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态：1启用 0禁用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dictionary_id` (`dictionary_id`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `uk_dict_value` (`dictionary_id`, `value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据字典项表';

-- ============================================================
-- 文件管理表
-- ============================================================
CREATE TABLE IF NOT EXISTS `files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '文件名',
  `path` varchar(500) NOT NULL COMMENT '相对路径',
  `url` varchar(500) NOT NULL COMMENT '完整URL',
  `mime_type` varchar(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `extension` varchar(20) NOT NULL DEFAULT '' COMMENT '文件扩展名',
  `size` bigint unsigned NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
  `group` varchar(100) NOT NULL DEFAULT '默认' COMMENT '分组',
  `upload_by` int unsigned NOT NULL DEFAULT 0 COMMENT '上传者ID',
  `storage` varchar(50) NOT NULL DEFAULT 'local' COMMENT '存储方式：local/oss/cos/qiniu',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group`),
  KEY `idx_mime_type` (`mime_type`),
  KEY `idx_upload_by` (`upload_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件管理表';

-- ============================================================
-- 通知消息表
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '通知标题',
  `content` text COMMENT '通知内容',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型：1系统通知 2待办提醒 3业务消息',
  `sender_id` int(10) unsigned DEFAULT NULL COMMENT '发送者ID（NULL为系统）',
  `target_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '目标类型：1全部 2指定用户',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1正常 0禁用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知消息表';

CREATE TABLE IF NOT EXISTS `notification_reads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int(10) unsigned NOT NULL COMMENT '通知ID',
  `admin_id` int(10) unsigned NOT NULL COMMENT '管理员ID',
  `read_at` datetime DEFAULT NULL COMMENT '阅读时间',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_admin` (`notification_id`, `admin_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知已读记录表';

-- ============================================================
-- 定时任务表
-- ============================================================
CREATE TABLE IF NOT EXISTS `cron_jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '任务名称',
  `command` varchar(255) NOT NULL COMMENT '执行命令（ThinkPHP命令）',
  `expression` varchar(100) NOT NULL COMMENT 'Cron表达式（如 */5 * * * *）',
  `description` varchar(255) DEFAULT NULL COMMENT '任务描述',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用 0禁用',
  `last_run_at` datetime DEFAULT NULL COMMENT '上次执行时间',
  `last_result` text COMMENT '上次执行结果',
  `last_status` tinyint(1) DEFAULT NULL COMMENT '上次执行状态：1成功 0失败',
  `run_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '累计执行次数',
  `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_by` int(10) unsigned DEFAULT NULL COMMENT '创建人',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务表';

CREATE TABLE IF NOT EXISTS `cron_job_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cron_job_id` int(10) unsigned NOT NULL COMMENT '任务ID',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1成功 0失败',
  `output` text COMMENT '输出内容',
  `error` text COMMENT '错误信息',
  `started_at` datetime DEFAULT NULL COMMENT '开始时间',
  `finished_at` datetime DEFAULT NULL COMMENT '结束时间',
  `duration` int(10) unsigned DEFAULT 0 COMMENT '执行耗时(毫秒)',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cron_job_id` (`cron_job_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务执行日志表';

-- ============================================================
-- 支付订单表
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(64) NOT NULL COMMENT '商户订单号',
  `trade_no` varchar(128) DEFAULT NULL COMMENT '第三方交易号',
  `channel` varchar(20) NOT NULL COMMENT '支付渠道：alipay/wechat',
  `trade_type` varchar(20) NOT NULL DEFAULT 'native' COMMENT '交易类型：native/jsapi/h5/app/page/wap',
  `subject` varchar(255) NOT NULL DEFAULT '' COMMENT '订单标题',
  `body` varchar(500) DEFAULT NULL COMMENT '订单描述',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额(元)',
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '退款金额(元)',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending/paid/closed/refunded',
  `notify_data` text COMMENT '回调原始数据(JSON)',
  `extra` text COMMENT '扩展数据(JSON)',
  `paid_at` datetime DEFAULT NULL COMMENT '支付时间',
  `refunded_at` datetime DEFAULT NULL COMMENT '退款时间',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_trade_no` (`trade_no`),
  KEY `idx_channel` (`channel`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付订单表';

-- ============================================================
-- 消息模板表
-- ============================================================
CREATE TABLE IF NOT EXISTS `message_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `code` varchar(50) NOT NULL COMMENT '模板标识',
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用短信',
  `sms_template_id` varchar(100) DEFAULT '' COMMENT '短信模板ID',
  `sms_content` varchar(500) DEFAULT '' COMMENT '短信内容预览',
  `wechat_official_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用公众号模板消息',
  `wechat_official_template_id` varchar(100) DEFAULT '' COMMENT '公众号模板ID',
  `wechat_official_url` varchar(500) DEFAULT '' COMMENT '模板消息跳转URL',
  `wechat_mini_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用小程序订阅消息',
  `wechat_mini_template_id` varchar(100) DEFAULT '' COMMENT '小程序模板ID',
  `wechat_mini_page` varchar(200) DEFAULT '' COMMENT '小程序跳转页面',
  `variables` json DEFAULT NULL COMMENT '模板变量定义',
  `remark` varchar(500) DEFAULT '' COMMENT '备注',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1启用,0禁用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息模板表';

-- ============================================================
-- 消息发送记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `message_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint(20) unsigned DEFAULT NULL COMMENT '模板ID',
  `template_code` varchar(50) DEFAULT '' COMMENT '模板标识',
  `channel` varchar(20) NOT NULL COMMENT '发送通道:sms,wechat_official,wechat_mini',
  `receiver` varchar(200) NOT NULL COMMENT '接收者',
  `content` text COMMENT '发送内容',
  `variables` json DEFAULT NULL COMMENT '模板变量值',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待发送,1成功,2失败',
  `error_msg` varchar(500) DEFAULT '' COMMENT '错误信息',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT '发送时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_channel` (`channel`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息发送记录表';

-- ============================================================
-- 微信自动回复表
-- ============================================================
CREATE TABLE IF NOT EXISTS `wechat_auto_replies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL COMMENT '类型:keyword,subscribe,default',
  `keyword` varchar(200) DEFAULT '' COMMENT '关键词',
  `match_type` varchar(10) DEFAULT 'exact' COMMENT '匹配方式:exact,fuzzy',
  `reply_type` varchar(10) DEFAULT 'text' COMMENT '回复类型:text,image,news',
  `content` text COMMENT '回复内容',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1启用,0禁用',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_keyword` (`keyword`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='微信自动回复表';

-- ============================================================
-- 用户表（C端移动端用户）
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `password` varchar(255) DEFAULT NULL COMMENT '密码',
  `gender` tinyint(1) NOT NULL DEFAULT 0 COMMENT '性别：0未知 1男 2女',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `openid` varchar(128) DEFAULT NULL COMMENT '微信openid',
  `unionid` varchar(128) DEFAULT NULL COMMENT '微信unionid',
  `mini_openid` varchar(128) DEFAULT NULL COMMENT '小程序openid',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int(11) NOT NULL DEFAULT 0 COMMENT '登录次数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1正常 0禁用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  KEY `idx_openid` (`openid`),
  KEY `idx_unionid` (`unionid`),
  KEY `idx_mini_openid` (`mini_openid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ============================================================
-- 协议表
-- ============================================================
CREATE TABLE IF NOT EXISTS `agreements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '标题',
  `code` varchar(50) NOT NULL COMMENT '协议编码',
  `content` text COMMENT '内容',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='协议表';

-- ============================================================
-- 公告表
-- ============================================================
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '标题',
  `content` text COMMENT '内容',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型：1通知 2更新 3活动',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0草稿 1已发布',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `publish_at` datetime DEFAULT NULL COMMENT '发布时间',
  `admin_id` int(10) unsigned NOT NULL COMMENT '管理员ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公告表';

-- ============================================================
-- 用户反馈表
-- ============================================================
CREATE TABLE IF NOT EXISTS `feedbacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(30) NOT NULL DEFAULT 'suggestion' COMMENT '类型：suggestion/bug/complaint/other',
  `content` text COMMENT '反馈内容',
  `images` text COMMENT '图片路径JSON数组',
  `contact` varchar(100) DEFAULT NULL COMMENT '联系方式',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待处理 1处理中 2已回复 3已关闭',
  `reply` text COMMENT '管理员回复',
  `replied_at` datetime DEFAULT NULL COMMENT '回复时间',
  `replied_by` int(10) unsigned DEFAULT NULL COMMENT '回复人ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户反馈表';

-- ============================================================
-- 地区表
-- ============================================================
CREATE TABLE IF NOT EXISTS `regions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '父级ID',
  `name` varchar(50) NOT NULL COMMENT '名称',
  `code` varchar(20) NOT NULL DEFAULT '' COMMENT '编码',
  `level` tinyint(1) NOT NULL DEFAULT 1 COMMENT '层级：1省 2市 3区',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='地区表';

-- ============================================================
-- APP版本表
-- ============================================================
CREATE TABLE IF NOT EXISTS `app_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `platform` varchar(20) NOT NULL COMMENT '平台',
  `version` varchar(20) NOT NULL COMMENT '版本号',
  `version_code` int(10) unsigned NOT NULL COMMENT '版本编码',
  `download_url` varchar(500) NOT NULL DEFAULT '' COMMENT '下载地址',
  `description` text COMMENT '版本描述',
  `force_update` tinyint(1) NOT NULL DEFAULT 0 COMMENT '强制更新：0否 1是',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_platform_version` (`platform`, `version_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APP版本表';

-- ============================================================
-- 数据导入表
-- ============================================================
CREATE TABLE IF NOT EXISTS `data_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL COMMENT '模块',
  `filename` varchar(200) NOT NULL COMMENT '文件名',
  `total_count` int(11) NOT NULL DEFAULT 0 COMMENT '总条数',
  `success_count` int(11) NOT NULL DEFAULT 0 COMMENT '成功条数',
  `fail_count` int(11) NOT NULL DEFAULT 0 COMMENT '失败条数',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0处理中 1完成 2失败',
  `errors` text COMMENT '错误信息JSON',
  `admin_id` int(10) unsigned NOT NULL COMMENT '管理员ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据导入表';

-- ============================================================
-- 用户站内通知表
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID，0为全体',
  `title` varchar(200) NOT NULL COMMENT '标题',
  `content` text COMMENT '内容',
  `type` varchar(30) NOT NULL DEFAULT 'system' COMMENT '类型：system/order/payment/feedback',
  `biz_id` int(11) DEFAULT NULL COMMENT '关联业务ID',
  `extra` text COMMENT '额外数据JSON',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户站内通知表';

-- ============================================================
-- 用户通知已读记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_notification_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int(10) unsigned NOT NULL COMMENT '通知ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `read_at` datetime DEFAULT NULL COMMENT '阅读时间',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_notification_user_unique` (`notification_id`, `user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户通知已读记录表';

-- 文章栏目表
CREATE TABLE IF NOT EXISTS `article_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父栏目ID',
  `name` varchar(100) NOT NULL COMMENT '栏目名称',
  `icon` varchar(255) DEFAULT '' COMMENT '栏目图标',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章栏目表';

-- 文章表
CREATE TABLE IF NOT EXISTS `articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL COMMENT '栏目ID',
  `title` varchar(200) NOT NULL COMMENT '标题',
  `cover` varchar(255) DEFAULT '' COMMENT '封面图',
  `summary` varchar(500) DEFAULT '' COMMENT '摘要',
  `content` longtext NOT NULL COMMENT '内容',
  `tags` varchar(500) DEFAULT '[]' COMMENT '标签JSON数组',
  `author` varchar(50) DEFAULT '' COMMENT '作者',
  `view_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '阅读量',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态:0草稿,1已发布',
  `publish_at` datetime DEFAULT NULL COMMENT '发布时间',
  `admin_id` int(10) unsigned NOT NULL COMMENT '创建管理员ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_publish_at` (`publish_at`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表';
