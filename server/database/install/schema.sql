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
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否出现在 config/global（前端公开）',
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

-- ---------------------------------------------------------------- M1b：数据字典

CREATE TABLE `dictionaries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '字典名称',
  `code` varchar(100) NOT NULL COMMENT '字典编码（字母、数字、下划线、短横线）',
  `description` varchar(500) DEFAULT '' COMMENT '描述',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `sort` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='数据字典';

CREATE TABLE `dictionary_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dictionary_id` int unsigned NOT NULL COMMENT '所属字典',
  `label` varchar(100) NOT NULL COMMENT '显示文本',
  `value` varchar(100) NOT NULL COMMENT '值（同一字典内唯一）',
  `tag_type` varchar(50) DEFAULT '' COMMENT '标签类型 success/warning/danger/info',
  `description` varchar(500) DEFAULT '' COMMENT '描述',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `sort` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dict_value` (`dictionary_id`, `value`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='数据字典项';

-- ---------------------------------------------------------------- M1b：操作日志

CREATE TABLE `admin_operation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '管理员ID',
  `username` varchar(50) NOT NULL DEFAULT '' COMMENT '用户名',
  `method` varchar(10) NOT NULL DEFAULT '' COMMENT '请求方法',
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '请求路径',
  `ip` varchar(45) NOT NULL DEFAULT '' COMMENT '操作IP',
  `user_agent` text COMMENT '用户代理',
  `action` varchar(100) NOT NULL DEFAULT '' COMMENT '操作动作',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '操作描述',
  `params` json DEFAULT NULL COMMENT '请求参数（已脱敏）',
  `result` json DEFAULT NULL COMMENT '操作结果 {code, message}',
  `operation_time` datetime NOT NULL COMMENT '操作时间',
  `execution_time` decimal(8,3) DEFAULT NULL COMMENT '执行时间(秒)',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_username` (`username`),
  KEY `idx_method` (`method`),
  KEY `idx_path` (`path`),
  KEY `idx_action` (`action`),
  KEY `idx_operation_time` (`operation_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员操作日志';

-- ---------------------------------------------------------------- M1b：站内通知

CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '标题',
  `content` text COMMENT '内容',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '1系统通知 2待办提醒 3业务消息',
  `sender_id` int unsigned DEFAULT NULL COMMENT '发送人（管理员 id，NULL 为系统）',
  `target_type` tinyint NOT NULL DEFAULT 1 COMMENT '1全部 2指定用户（M1 只支持 1，2 在 M4 实现）',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1已发布 0草稿',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_status_target` (`status`, `target_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='站内通知';

CREATE TABLE `notification_reads` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int unsigned NOT NULL,
  `admin_id` int unsigned NOT NULL,
  `read_at` datetime DEFAULT NULL COMMENT '阅读时间',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_admin` (`notification_id`, `admin_id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='通知已读记录';

-- ---------------------------------------------------------------- M1c：素材与上传

CREATE TABLE `files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '文件名',
  `path` varchar(500) NOT NULL COMMENT '相对路径',
  `url` varchar(500) NOT NULL COMMENT '访问URL（本地为 /storage/… 相对路径，云存储为完整URL）',
  `mime_type` varchar(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `extension` varchar(20) NOT NULL DEFAULT '' COMMENT '文件扩展名',
  `size` bigint unsigned NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
  `group` varchar(100) NOT NULL DEFAULT '默认' COMMENT '分组（MySQL 保留字，原生 SQL 里须加反引号）',
  `upload_by` int unsigned NOT NULL DEFAULT 0 COMMENT '上传者ID',
  `storage` varchar(50) NOT NULL DEFAULT 'local' COMMENT '存储方式：local/aliyun/tencent/qiniu',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group`),
  KEY `idx_mime_type` (`mime_type`),
  KEY `idx_upload_by` (`upload_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文件管理表';

-- ---------------------------------------------------------------- M3：调度器与队列

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(100) NOT NULL COMMENT '队列名',
  `payload` json NOT NULL COMMENT '任务数据（已脱敏；queue:retry 按它重新投递）',
  `exception` text COMMENT '异常类名、消息与调用栈（截断到 5000 字）',
  `attempts` int unsigned NOT NULL DEFAULT 0 COMMENT '已尝试次数',
  `failed_at` datetime NOT NULL COMMENT '最后一次失败时间',
  PRIMARY KEY (`id`),
  KEY `idx_queue` (`queue`),
  KEY `idx_failed_at` (`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='队列失败任务';

CREATE TABLE `cron_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '任务名称',
  `command` varchar(255) NOT NULL COMMENT '执行命令：config/cron.php 白名单里的控制台命令及参数，如 log:archive --days=90',
  `expression` varchar(100) NOT NULL COMMENT 'Cron 表达式（5 段：分 时 日 月 周，如 */5 * * * *）',
  `description` varchar(255) DEFAULT NULL COMMENT '任务描述',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `last_run_at` datetime DEFAULT NULL COMMENT '上次执行时间',
  `last_status` tinyint DEFAULT NULL COMMENT '上次执行结果：1成功 0失败',
  `last_result` varchar(500) DEFAULT NULL COMMENT '上次输出或错误（截断到 500 字）',
  `run_count` int unsigned NOT NULL DEFAULT 0 COMMENT '累计执行次数',
  `sort` int NOT NULL DEFAULT 0,
  `created_by` int unsigned DEFAULT NULL COMMENT '创建人（不接数据权限，由 Service 显式写入）',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='定时任务';

CREATE TABLE `cron_job_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cron_job_id` int unsigned NOT NULL COMMENT '所属任务',
  `trigger` tinyint NOT NULL DEFAULT 1 COMMENT '触发方式：1定时 2手动（MySQL 保留字，原生 SQL 里须加反引号）',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1成功 0失败（执行结束才写这一行，没有「执行中」）',
  `output` mediumtext COMMENT '命令输出（截断到 60000 字）',
  `error` mediumtext COMMENT '错误信息（截断到 60000 字）',
  `started_at` datetime NOT NULL COMMENT '开始时间',
  `finished_at` datetime NOT NULL COMMENT '结束时间',
  `duration` int unsigned NOT NULL DEFAULT 0 COMMENT '耗时（毫秒）',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cron_job_id` (`cron_job_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='定时任务执行日志';

-- ---------------------------------------------------------------- M5a：会员与资产

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `password` varchar(255) DEFAULT NULL COMMENT '密码（password_hash）',
  `gender` tinyint NOT NULL DEFAULT 0 COMMENT '性别:0未知 1男 2女',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `openid` varchar(128) DEFAULT NULL COMMENT '微信openid',
  `oa_openid` varchar(128) DEFAULT NULL COMMENT '公众号openid',
  `unionid` varchar(128) DEFAULT NULL COMMENT '微信unionid',
  `mini_openid` varchar(128) DEFAULT NULL COMMENT '小程序openid',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int unsigned NOT NULL DEFAULT 0 COMMENT '登录次数',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态:1正常 0禁用',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '余额',
  `points` int NOT NULL DEFAULT 0 COMMENT '积分',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  KEY `idx_openid` (`openid`),
  KEY `idx_oa_openid` (`oa_openid`),
  KEY `idx_unionid` (`unionid`),
  KEY `idx_mini_openid` (`mini_openid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='会员';

CREATE TABLE `balance_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL COMMENT '会员ID',
  `amount` decimal(10,2) NOT NULL COMMENT '变动金额',
  `before_balance` decimal(10,2) NOT NULL COMMENT '变动前余额',
  `after_balance` decimal(10,2) NOT NULL COMMENT '变动后余额',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '类型:1充值 2消费 3退款 4后台调整',
  `source` varchar(50) NOT NULL DEFAULT '' COMMENT '来源标识',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `operator_id` int unsigned DEFAULT NULL COMMENT '操作管理员ID',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='余额变动记录';

CREATE TABLE `points_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL COMMENT '会员ID',
  `points` int NOT NULL COMMENT '变动积分',
  `before_points` int NOT NULL COMMENT '变动前积分',
  `after_points` int NOT NULL COMMENT '变动后积分',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '类型:1后台调整 2注册赠送 3签到 4消费赠送 5消费扣减',
  `source` varchar(50) NOT NULL DEFAULT '' COMMENT '来源标识',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `operator_id` int unsigned DEFAULT NULL COMMENT '操作管理员ID',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='积分变动记录';

CREATE TABLE `payment_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL COMMENT '会员ID',
  `biz_type` varchar(30) NOT NULL COMMENT '业务类型：recharge',
  `client_type` varchar(20) NOT NULL COMMENT '客户端：pc/h5/app/wechat_h5/miniapp',
  `order_no` varchar(32) NOT NULL COMMENT '商户订单号',
  `app_id` varchar(32) DEFAULT NULL COMMENT '下单所用 appid（微信）',
  `trade_no` varchar(64) DEFAULT NULL COMMENT '渠道交易号',
  `channel` varchar(20) NOT NULL COMMENT '支付渠道：wechat/alipay',
  `trade_type` varchar(20) NOT NULL COMMENT '交易类型：native/h5/app/jsapi/page/wap',
  `subject` varchar(128) NOT NULL COMMENT '订单标题',
  `amount_cents` int unsigned NOT NULL COMMENT '应付金额（分）',
  `refunded_cents` int unsigned NOT NULL DEFAULT 0 COMMENT '累计退款成功金额（分）',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending/paid/closed/refunded',
  `error_msg` varchar(255) DEFAULT NULL COMMENT '下单明确失败的原因',
  `expires_at` datetime NOT NULL COMMENT '支付截止时间',
  `paid_at` datetime DEFAULT NULL COMMENT '支付完成时间',
  `closed_at` datetime DEFAULT NULL COMMENT '关闭时间',
  `notify_data` json DEFAULT NULL COMMENT '置已支付时的渠道原始数据（解密后）',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='支付订单';

CREATE TABLE `refund_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `refund_no` varchar(32) NOT NULL COMMENT '商户退款单号（微信 out_refund_no / 支付宝 out_request_no）',
  `payment_order_id` bigint unsigned NOT NULL COMMENT '支付订单ID',
  `amount_cents` int unsigned NOT NULL COMMENT '退款金额（分）',
  `reason` varchar(80) NOT NULL DEFAULT '' COMMENT '退款原因',
  `status` varchar(20) NOT NULL COMMENT '状态：processing/success/failed',
  `channel_refund_no` varchar(64) DEFAULT NULL COMMENT '渠道退款单号',
  `error_msg` varchar(255) DEFAULT NULL COMMENT '失败原因',
  `operator` varchar(64) NOT NULL COMMENT '执行者，命令行为 cli:{系统用户名}',
  `refunded_at` datetime DEFAULT NULL COMMENT '退款成功时间',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_refund_no` (`refund_no`),
  KEY `idx_payment_order` (`payment_order_id`),
  KEY `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='退款单';

CREATE TABLE `message_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `code` varchar(50) NOT NULL COMMENT '模板编码（唯一，含软删行）',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态：1启用 0停用',
  `sms_enabled` tinyint NOT NULL DEFAULT 0 COMMENT '短信通道开关',
  `sms_template_id` varchar(100) NOT NULL DEFAULT '' COMMENT '短信模板ID（阿里云 TemplateCode / 腾讯云 TemplateId）',
  `sms_content` varchar(500) NOT NULL DEFAULT '' COMMENT '短信内容预览（不参与渲染）',
  `wechat_official_enabled` tinyint NOT NULL DEFAULT 0 COMMENT '公众号模板消息开关',
  `wechat_official_template_id` varchar(100) NOT NULL DEFAULT '' COMMENT '公众号模板ID',
  `wechat_official_url` varchar(500) NOT NULL DEFAULT '' COMMENT '公众号跳转URL（可含变量）',
  `wechat_official_data` json DEFAULT NULL COMMENT '公众号字段映射 {字段: 文本}',
  `wechat_mini_enabled` tinyint NOT NULL DEFAULT 0 COMMENT '小程序订阅消息开关',
  `wechat_mini_template_id` varchar(100) NOT NULL DEFAULT '' COMMENT '小程序模板ID',
  `wechat_mini_page` varchar(200) NOT NULL DEFAULT '' COMMENT '小程序跳转页面（可含变量）',
  `wechat_mini_data` json DEFAULT NULL COMMENT '小程序字段映射 {字段: 文本}',
  `site_enabled` tinyint NOT NULL DEFAULT 0 COMMENT '站内信开关',
  `site_title` varchar(100) NOT NULL DEFAULT '' COMMENT '站内信标题（可含变量）',
  `site_content` varchar(500) NOT NULL DEFAULT '' COMMENT '站内信正文（可含变量）',
  `variables` json DEFAULT NULL COMMENT '变量定义 [{key, name, example}]',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='消息模板';

CREATE TABLE `message_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned DEFAULT NULL COMMENT '模板ID',
  `template_code` varchar(50) NOT NULL COMMENT '模板编码',
  `channel` varchar(20) NOT NULL COMMENT '通道：sms/wechat_official/wechat_mini/site',
  `user_id` bigint unsigned DEFAULT NULL COMMENT '会员ID（发送时据此重读接收人）',
  `receiver` varchar(64) NOT NULL DEFAULT '' COMMENT '遮蔽后的接收人',
  `variables` json DEFAULT NULL COMMENT '渲染变量',
  `content` text COMMENT '渲染结果',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '状态：0待发 1成功 2失败',
  `error_msg` varchar(255) NOT NULL DEFAULT '' COMMENT '失败原因（errcode/固定文案/异常类名）',
  `attempts` int unsigned NOT NULL DEFAULT 0 COMMENT '发送尝试次数',
  `sent_at` datetime DEFAULT NULL COMMENT '发送成功时间',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_channel_created` (`channel`,`created_at`),
  KEY `idx_template_code` (`template_code`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='消息发送日志';

CREATE TABLE `user_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT '会员ID',
  `title` varchar(100) NOT NULL COMMENT '标题',
  `content` varchar(500) NOT NULL COMMENT '正文',
  `type` varchar(20) NOT NULL COMMENT '展示分类：system/order/payment/activity',
  `biz_id` varchar(64) NOT NULL DEFAULT '' COMMENT '业务标识',
  `extra` json DEFAULT NULL COMMENT '扩展信息',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='会员站内信';

CREATE TABLE `user_notification_reads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint unsigned NOT NULL COMMENT '站内信ID',
  `user_id` bigint unsigned NOT NULL COMMENT '会员ID',
  `read_at` datetime NOT NULL COMMENT '阅读时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_user` (`notification_id`,`user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='会员站内信已读记录';

CREATE TABLE `wechat_auto_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL COMMENT 'keyword/subscribe/default',
  `keyword` varchar(200) NOT NULL DEFAULT '' COMMENT '关键词；非 keyword 类型为空串',
  `match_type` varchar(10) NOT NULL DEFAULT 'exact' COMMENT 'exact/fuzzy',
  `reply_type` varchar(10) NOT NULL DEFAULT 'text' COMMENT '本阶段只写 text',
  `content` text NOT NULL COMMENT '回复正文',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `sort_order` int NOT NULL DEFAULT 0 COMMENT '越小越先匹配',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_keyword` (`keyword`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='微信自动回复';

CREATE TABLE `article_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT 0 COMMENT '父栏目ID',
  `name` varchar(100) NOT NULL COMMENT '栏目名称',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '栏目图标',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态:1启用 0禁用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文章栏目';

CREATE TABLE `articles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL COMMENT '栏目ID',
  `title` varchar(200) NOT NULL COMMENT '标题',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `content` longtext NOT NULL COMMENT '内容',
  `tags` json DEFAULT NULL COMMENT '标签JSON数组',
  `author` varchar(50) NOT NULL DEFAULT '' COMMENT '作者',
  `view_count` int unsigned NOT NULL DEFAULT 0 COMMENT '阅读量',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0草稿 1已发布',
  `publish_at` datetime DEFAULT NULL COMMENT '发布时间',
  `created_by` int unsigned DEFAULT NULL COMMENT '创建人（不接数据权限）',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_publish_at` (`publish_at`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文章';

CREATE TABLE `announcements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '标题',
  `content` text COMMENT '内容',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '1通知 2更新 3活动',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0草稿 1已发布',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `publish_at` datetime DEFAULT NULL COMMENT '发布时间',
  `created_by` int unsigned DEFAULT NULL COMMENT '创建人（不接数据权限）',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='公告';

CREATE TABLE `agreements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '标题',
  `code` varchar(50) NOT NULL COMMENT '协议编码',
  `content` text COMMENT '内容',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '0禁用 1启用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='协议';

CREATE TABLE `feedbacks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(30) NOT NULL DEFAULT 'suggestion' COMMENT 'suggestion/bug/complaint/other',
  `content` text COMMENT '反馈内容',
  `images` json DEFAULT NULL COMMENT '图片URL数组',
  `contact` varchar(100) DEFAULT NULL COMMENT '联系方式',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0待处理 1处理中 2已回复 3已关闭',
  `reply` text COMMENT '管理员回复',
  `replied_at` datetime DEFAULT NULL COMMENT '回复时间',
  `replied_by` int unsigned DEFAULT NULL COMMENT '回复人',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户反馈';

CREATE TABLE `regions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT 0 COMMENT '父级ID',
  `name` varchar(50) NOT NULL COMMENT '名称',
  `code` varchar(20) NOT NULL DEFAULT '' COMMENT '编码',
  `level` tinyint NOT NULL DEFAULT 1 COMMENT '层级：1省 2市 3区',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='地区表';

CREATE TABLE `app_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `platform` varchar(20) NOT NULL COMMENT '平台',
  `version` varchar(20) NOT NULL COMMENT '版本号',
  `version_code` int unsigned NOT NULL COMMENT '版本编码',
  `download_url` varchar(500) NOT NULL DEFAULT '' COMMENT '下载地址',
  `description` text COMMENT '版本描述',
  `force_update` tinyint NOT NULL DEFAULT 0 COMMENT '强制更新：0否 1是',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_platform_version` (`platform`, `version_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='APP版本表';

CREATE TABLE `data_imports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL COMMENT '模块',
  `filename` varchar(200) NOT NULL COMMENT '文件名',
  `total_count` int NOT NULL DEFAULT 0 COMMENT '总条数',
  `success_count` int NOT NULL DEFAULT 0 COMMENT '成功条数',
  `fail_count` int NOT NULL DEFAULT 0 COMMENT '失败条数',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '状态：0处理中 1完成 2失败',
  `errors` text COMMENT '错误信息JSON',
  `admin_id` int unsigned NOT NULL COMMENT '管理员ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='数据导入表';

CREATE TABLE `diy_pages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `page_type` varchar(32) NOT NULL DEFAULT 'home' COMMENT '页面类型:home/member/custom',
  `page_key` varchar(64) NOT NULL DEFAULT '' COMMENT '页面标识(slug);home固定home',
  `platform` varchar(16) NOT NULL DEFAULT 'uniapp' COMMENT '端:uniapp/pc',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '页面名称',
  `components_draft` json DEFAULT NULL COMMENT '草稿组件树',
  `components_published` json DEFAULT NULL COMMENT '已发布组件树',
  `page_settings` json DEFAULT NULL COMMENT '页面设置',
  `status` tinyint DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pagekey_platform` (`page_key`,`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面表';

CREATE TABLE `diy_page_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'diy_pages.id',
  `version_no` int NOT NULL DEFAULT '1' COMMENT '版本号(按page递增)',
  `components` json DEFAULT NULL COMMENT '组件树快照',
  `page_settings` json DEFAULT NULL COMMENT '页面设置快照',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` int unsigned DEFAULT NULL COMMENT '操作人admin_id，不是数据范围',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_page_version` (`page_id`,`version_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面版本快照表';

CREATE TABLE `diy_links` (
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

CREATE TABLE `mobile_configs` (
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
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='移动端配置（主题/tabBar）';

CREATE TABLE `system_upgrades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(32) NOT NULL COMMENT '已应用版本，如 2.0.0',
  `applied_at` datetime NOT NULL COMMENT '打标时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='框架升级记录';
