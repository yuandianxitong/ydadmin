-- ============================================================
-- 元点Admin - 初始数据
-- ============================================================

-- 插入超级管理员角色
INSERT INTO `roles` (`id`, `name`, `title`, `description`, `data_scope`, `is_system`, `status`, `sort`, `created_at`, `updated_at`) VALUES
    (1, 'super_admin', '超级管理员', '系统超级管理员，拥有所有权限', 1, 1, 1, 0, NOW(), NOW());

-- ============================================================
-- 权限数据
-- ============================================================
INSERT INTO `permissions` (`id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`) VALUES
    -- 系统管理（原有）
    (1, 'system', '系统管理', '系统管理', '系统管理权限', 'admin', 1, 0, NOW(), NOW()),
    -- 管理员管理
    (2, 'system.admin', '管理员管理', '系统管理', '管理员管理权限', 'admin', 1, 1, NOW(), NOW()),
    (3, 'system.admin.list', '管理员列表', '系统管理', '查看管理员列表', 'admin', 1, 2, NOW(), NOW()),
    (4, 'system.admin.create', '创建管理员', '系统管理', '创建新管理员', 'admin', 1, 3, NOW(), NOW()),
    (5, 'system.admin.update', '编辑管理员', '系统管理', '编辑管理员信息', 'admin', 1, 4, NOW(), NOW()),
    (6, 'system.admin.delete', '删除管理员', '系统管理', '删除管理员', 'admin', 1, 5, NOW(), NOW()),
    (7, 'system.admin.status', '管理员状态', '系统管理', '修改管理员状态', 'admin', 1, 6, NOW(), NOW()),
    -- 角色管理
    (8, 'system.role', '角色管理', '系统管理', '角色管理权限', 'admin', 1, 7, NOW(), NOW()),
    (9, 'system.role.list', '角色列表', '系统管理', '查看角色列表', 'admin', 1, 8, NOW(), NOW()),
    (10, 'system.role.create', '创建角色', '系统管理', '创建新角色', 'admin', 1, 9, NOW(), NOW()),
    (11, 'system.role.update', '编辑角色', '系统管理', '编辑角色信息', 'admin', 1, 10, NOW(), NOW()),
    (12, 'system.role.delete', '删除角色', '系统管理', '删除角色', 'admin', 1, 11, NOW(), NOW()),
    (13, 'system.role.permission', '角色授权', '系统管理', '为角色分配权限', 'admin', 1, 12, NOW(), NOW()),
    -- 权限管理
    (14, 'system.permission', '权限管理', '系统管理', '权限管理', 'admin', 1, 13, NOW(), NOW()),
    (15, 'system.permission.list', '权限列表', '系统管理', '查看权限列表', 'admin', 1, 14, NOW(), NOW()),
    (16, 'system.permission.create', '创建权限', '系统管理', '创建新权限', 'admin', 1, 15, NOW(), NOW()),
    (17, 'system.permission.update', '编辑权限', '系统管理', '编辑权限信息', 'admin', 1, 16, NOW(), NOW()),
    (18, 'system.permission.delete', '删除权限', '系统管理', '删除权限', 'admin', 1, 17, NOW(), NOW()),
    -- 菜单管理
    (19, 'system.menu', '菜单管理', '系统管理', '菜单管理权限', 'admin', 1, 18, NOW(), NOW()),
    (20, 'system.menu.list', '菜单列表', '系统管理', '查看菜单列表', 'admin', 1, 19, NOW(), NOW()),
    (21, 'system.menu.create', '创建菜单', '系统管理', '创建新菜单', 'admin', 1, 20, NOW(), NOW()),
    (22, 'system.menu.update', '编辑菜单', '系统管理', '编辑菜单信息', 'admin', 1, 21, NOW(), NOW()),
    (23, 'system.menu.delete', '删除菜单', '系统管理', '删除菜单', 'admin', 1, 22, NOW(), NOW()),
    -- 日志管理
    (24, 'system.log', '日志管理', '系统管理', '日志管理权限', 'admin', 1, 23, NOW(), NOW()),
    (25, 'system.log.login', '登录日志', '系统管理', '查看登录日志', 'admin', 1, 24, NOW(), NOW()),
    (26, 'system.log.operation', '操作日志', '系统管理', '查看操作日志', 'admin', 1, 25, NOW(), NOW()),
    -- 部门管理
    (27, 'system.department', '部门管理', '系统管理', '部门管理权限', 'admin', 1, 26, NOW(), NOW()),
    (28, 'system.department.list', '部门列表', '系统管理', '查看部门列表', 'admin', 1, 27, NOW(), NOW()),
    (29, 'system.department.create', '创建部门', '系统管理', '创建新部门', 'admin', 1, 28, NOW(), NOW()),
    (30, 'system.department.update', '编辑部门', '系统管理', '编辑部门信息', 'admin', 1, 29, NOW(), NOW()),
    (31, 'system.department.delete', '删除部门', '系统管理', '删除部门', 'admin', 1, 30, NOW(), NOW()),
    -- 数据字典
    (32, 'system.dictionary', '数据字典', '系统管理', '数据字典管理权限', 'admin', 1, 31, NOW(), NOW()),
    (33, 'system.dictionary.list', '字典列表', '系统管理', '查看字典列表', 'admin', 1, 32, NOW(), NOW()),
    (34, 'system.dictionary.create', '创建字典', '系统管理', '创建数据字典', 'admin', 1, 33, NOW(), NOW()),
    (35, 'system.dictionary.update', '编辑字典', '系统管理', '编辑数据字典', 'admin', 1, 34, NOW(), NOW()),
    (36, 'system.dictionary.delete', '删除字典', '系统管理', '删除数据字典', 'admin', 1, 35, NOW(), NOW()),
    -- 文件管理
    (37, 'system.file', '文件管理', '系统管理', '文件管理权限', 'admin', 1, 36, NOW(), NOW()),
    (38, 'system.file.list', '文件列表', '系统管理', '查看文件列表', 'admin', 1, 37, NOW(), NOW()),
    (39, 'system.file.delete', '删除文件', '系统管理', '删除文件', 'admin', 1, 38, NOW(), NOW()),
    -- 通知管理
    (40, 'system.notification', '通知管理', '系统管理', '通知管理权限', 'admin', 1, 39, NOW(), NOW()),
    (41, 'system.notification.list', '通知列表', '系统管理', '查看通知列表', 'admin', 1, 40, NOW(), NOW()),
    (42, 'system.notification.create', '发布通知', '系统管理', '发布系统通知', 'admin', 1, 41, NOW(), NOW()),
    (43, 'system.notification.update', '编辑通知', '系统管理', '编辑通知内容', 'admin', 1, 42, NOW(), NOW()),
    (44, 'system.notification.delete', '删除通知', '系统管理', '删除通知', 'admin', 1, 43, NOW(), NOW()),
    -- 定时任务
    (45, 'system.cron_job', '定时任务', '系统管理', '定时任务管理权限', 'admin', 1, 44, NOW(), NOW()),
    (46, 'system.cron_job.list', '任务列表', '系统管理', '查看定时任务列表', 'admin', 1, 45, NOW(), NOW()),
    (47, 'system.cron_job.create', '创建任务', '系统管理', '创建定时任务', 'admin', 1, 46, NOW(), NOW()),
    (48, 'system.cron_job.update', '编辑任务', '系统管理', '编辑定时任务', 'admin', 1, 47, NOW(), NOW()),
    (49, 'system.cron_job.delete', '删除任务', '系统管理', '删除定时任务', 'admin', 1, 48, NOW(), NOW()),
    (50, 'system.cron_job.run', '执行任务', '系统管理', '手动执行定时任务', 'admin', 1, 49, NOW(), NOW()),
    -- 系统配置
    (51, 'system.config', '系统配置', '系统管理', '系统配置管理权限', 'admin', 1, 50, NOW(), NOW()),
    (52, 'system.config.list', '配置列表', '系统管理', '查看系统配置', 'admin', 1, 51, NOW(), NOW()),
    (53, 'system.config.update', '修改配置', '系统管理', '修改系统配置', 'admin', 1, 52, NOW(), NOW()),
    -- 代码生成器
    (54, 'system.generator', '代码生成器', '开发工具', '代码生成器权限', 'admin', 1, 53, NOW(), NOW()),
    -- API文档
    (55, 'system.api_doc', 'API文档', '开发工具', 'API文档查看权限', 'admin', 1, 54, NOW(), NOW()),
    -- 消息管理（系统管理子模块）
    (60, 'system.message', '消息管理', '系统管理', '消息管理权限', 'admin', 1, 60, NOW(), NOW()),
    (61, 'system.message.template', '消息模板', '系统管理', '消息模板管理', 'admin', 1, 61, NOW(), NOW()),
    (62, 'system.message.template.list', '模板列表', '系统管理', '查看消息模板列表', 'admin', 1, 62, NOW(), NOW()),
    (63, 'system.message.template.create', '创建模板', '系统管理', '创建消息模板', 'admin', 1, 63, NOW(), NOW()),
    (64, 'system.message.template.update', '编辑模板', '系统管理', '编辑消息模板', 'admin', 1, 64, NOW(), NOW()),
    (65, 'system.message.template.delete', '删除模板', '系统管理', '删除消息模板', 'admin', 1, 65, NOW(), NOW()),
    (66, 'system.message.log', '发送记录', '系统管理', '查看发送记录', 'admin', 1, 66, NOW(), NOW()),
    -- 渠道管理
    (70, 'channel', '渠道管理', '渠道管理', '渠道管理权限', 'admin', 1, 70, NOW(), NOW()),
    (71, 'channel.official', '公众号管理', '渠道管理', '公众号管理权限', 'admin', 1, 71, NOW(), NOW()),
    (72, 'channel.official.config', '公众号配置', '渠道管理', '公众号配置管理', 'admin', 1, 72, NOW(), NOW()),
    (73, 'channel.official.menu', '自定义菜单', '渠道管理', '公众号菜单管理', 'admin', 1, 73, NOW(), NOW()),
    (74, 'channel.official.auto_reply', '自动回复', '渠道管理', '公众号自动回复管理', 'admin', 1, 74, NOW(), NOW()),
    (75, 'channel.miniapp', '小程序管理', '渠道管理', '小程序管理权限', 'admin', 1, 75, NOW(), NOW()),
    (76, 'channel.miniapp.config', '小程序配置', '渠道管理', '小程序配置管理', 'admin', 1, 76, NOW(), NOW());

-- ============================================================
-- 菜单数据
-- ============================================================
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  -- 控制台
  (1, 0, 2, '控制台', 'Workbench', '/workbench', 'workbench/index', NULL, 'el-icon-Odometer', NULL, 0, 1, 1, 0, NULL, 1, NULL, NULL, 1, 0, NOW(), NOW()),

  -- ===== 系统管理 =====
  (2, 0, 1, '系统管理', 'System', '/system', 'LAYOUT', NULL, 'el-icon-Setting', 'system', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 900, NOW(), NOW()),

  -- 管理员管理
  (10, 2, 2, '管理员管理', 'SystemAdmin', '/system/admin', '/system/admin/index', NULL, 'el-icon-User', 'system.admin', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (11, 10, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.admin.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (12, 10, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.admin.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (13, 10, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.admin.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 角色管理
  (20, 2, 2, '角色管理', 'SystemRole', '/system/role', '/system/role/index', NULL, 'el-icon-UserFilled', 'system.role', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (21, 20, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.role.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (22, 20, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.role.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (23, 20, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.role.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (24, 20, 3, '授权', NULL, NULL, NULL, NULL, NULL, 'system.role.permission', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- 部门管理
  (30, 2, 2, '部门管理', 'SystemDepartment', '/system/department', '/system/department/index', NULL, 'el-icon-OfficeBuilding', 'system.department', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (31, 30, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.department.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (32, 30, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.department.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (33, 30, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.department.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 权限管理
  (40, 2, 2, '权限管理', 'SystemPermission', '/system/permission', '/system/permission/index', NULL, 'el-icon-Lock', 'system.permission', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- 菜单管理
  (50, 2, 2, '菜单管理', 'SystemMenu', '/system/menu', '/system/menu/index', NULL, 'el-icon-Menu', 'system.menu', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),

  -- 数据字典
  (60, 2, 2, '数据字典', 'SystemDictionary', '/system/dictionary', '/system/dictionary/index', NULL, 'el-icon-Collection', 'system.dictionary', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 6, NOW(), NOW()),
  (61, 60, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (62, 60, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (63, 60, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 文件管理
  (70, 2, 2, '文件管理', 'SystemFile', '/system/file', '/system/file/index', NULL, 'el-icon-FolderOpened', 'system.file', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 7, NOW(), NOW()),
  (71, 70, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.file.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- 通知管理
  (80, 2, 2, '通知管理', 'SystemNotification', '/system/notification', '/system/notification/index', NULL, 'el-icon-Bell', 'system.notification', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 8, NOW(), NOW()),
  (81, 80, 3, '发布', NULL, NULL, NULL, NULL, NULL, 'system.notification.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (82, 80, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.notification.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (83, 80, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.notification.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 定时任务
  (90, 2, 2, '定时任务', 'SystemCronJob', '/system/cron-job', '/system/cron-job/index', NULL, 'el-icon-Timer', 'system.cron_job', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 9, NOW(), NOW()),
  (91, 90, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (92, 90, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (93, 90, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (94, 90, 3, '执行', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.run', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- 系统配置
  (100, 2, 2, '系统配置', 'SystemConfig', '/system/config', '/system/config/index', NULL, 'el-icon-Tools', 'system.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 10, NOW(), NOW()),

  -- 日志管理（目录）
  (110, 2, 1, '日志管理', 'SystemLog', '/system/log', 'LAYOUT', NULL, 'el-icon-Document', 'system.log', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 11, NOW(), NOW()),
  (111, 110, 2, '登录日志', 'SystemLoginLog', '/system/log/login', '/system/log/login', NULL, NULL, 'system.log.login', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (112, 110, 2, '操作日志', 'SystemOperationLog', '/system/log/operation', '/system/log/operation', NULL, NULL, 'system.log.operation', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),

  -- ===== 开发工具 =====
  (3, 0, 1, '开发工具', 'DevTools', '/dev-tools', 'LAYOUT', NULL, 'el-icon-Cpu', NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 950, NOW(), NOW()),

  -- 代码生成器
  (200, 3, 2, '代码生成器', 'DevGenerator', '/dev-tools/generator', '/system/generator/index', NULL, 'el-icon-MagicStick', 'system.generator', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- API文档
  (210, 3, 2, 'API文档', 'DevApiDoc', '/dev-tools/api-doc', '/system/api-doc/index', NULL, 'el-icon-Notebook', 'system.api_doc', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),

  -- ===== 消息管理（系统管理子模块） =====
  (120, 2, 1, '消息管理', 'SystemMessage', '/system/message', 'LAYOUT', '/system/message/template', 'el-icon-ChatDotRound', 'system.message', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 12, NOW(), NOW()),
  (121, 120, 2, '消息模板', 'SystemMessageTemplate', '/system/message/template', '/system/message/template/index', NULL, 'el-icon-Tickets', 'system.message.template', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (122, 120, 2, '发送记录', 'SystemMessageLog', '/system/message/log', '/system/message/log/index', NULL, 'el-icon-List', 'system.message.log', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),

  -- ===== 渠道管理 =====
  (4, 0, 1, '渠道管理', 'Channel', '/channel', 'LAYOUT', '/channel/official/config', 'el-icon-Promotion', 'channel', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 700, NOW(), NOW()),

  -- 公众号（目录）
  (5, 4, 1, '公众号', 'ChannelOfficial', '/channel/official', 'LAYOUT', '/channel/official/config', 'el-icon-Service', 'channel.official', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (400, 5, 2, '公众号配置', 'ChannelOfficialConfig', '/channel/official/config', '/channel/official/config', NULL, 'el-icon-Setting', 'channel.official.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (410, 5, 2, '自定义菜单', 'ChannelOfficialMenu', '/channel/official/menu', '/channel/official/menu', NULL, 'el-icon-Grid', 'channel.official.menu', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (420, 5, 2, '自动回复', 'ChannelAutoReply', '/channel/official/auto-reply', '/channel/official/auto-reply', NULL, 'el-icon-ChatSquare', 'channel.official.auto_reply', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 小程序（目录）
  (6, 4, 1, '小程序', 'ChannelMiniApp', '/channel/miniapp', 'LAYOUT', '/channel/miniapp/config', 'el-icon-Iphone', 'channel.miniapp', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (500, 6, 2, '小程序配置', 'ChannelMiniAppConfig', '/channel/miniapp/config', '/channel/miniapp/config', NULL, 'el-icon-Setting', 'channel.miniapp.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW());

-- ============================================================
-- 为超级管理员角色分配所有权限和菜单
-- ============================================================
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 1, id, NOW(), NOW() FROM `permissions`;

INSERT INTO `role_menus` (`role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT 1, id, NOW(), NOW() FROM `menus`;

-- ============================================================
-- 数据字典初始数据
-- ============================================================
INSERT INTO `dictionaries` (`name`, `code`, `description`, `status`, `sort`, `created_at`, `updated_at`) VALUES
('性别', 'gender', '用户性别', 1, 0, NOW(), NOW()),
('状态', 'common_status', '通用启用/禁用状态', 1, 1, NOW(), NOW());

INSERT INTO `dictionary_items` (`dictionary_id`, `label`, `value`, `tag_type`, `status`, `sort`, `created_at`, `updated_at`) VALUES
(1, '男', '1', '', 1, 0, NOW(), NOW()),
(1, '女', '2', '', 1, 1, NOW(), NOW()),
(1, '未知', '0', 'info', 1, 2, NOW(), NOW()),
(2, '启用', '1', 'success', 1, 0, NOW(), NOW()),
(2, '禁用', '0', 'danger', 1, 1, NOW(), NOW());

-- ============================================================
-- 部门初始数据
-- ============================================================
INSERT INTO `departments` (`id`, `parent_id`, `name`, `code`, `leader`, `sort`, `status`, `created_at`, `updated_at`) VALUES
(1, 0, '总公司', 'HQ', '管理员', 0, 1, NOW(), NOW()),
(2, 1, '技术部', 'TECH', NULL, 1, 1, NOW(), NOW()),
(3, 1, '市场部', 'MARKET', NULL, 2, 1, NOW(), NOW()),
(4, 1, '财务部', 'FINANCE', NULL, 3, 1, NOW(), NOW()),
(5, 2, '前端组', 'TECH-FE', NULL, 1, 1, NOW(), NOW()),
(6, 2, '后端组', 'TECH-BE', NULL, 2, 1, NOW(), NOW());

-- ============================================================
-- 定时任务示例数据
-- ============================================================
INSERT INTO `cron_jobs` (`name`, `command`, `expression`, `description`, `status`, `created_at`, `updated_at`) VALUES
('清理过期缓存', 'clear:cache', '0 2 * * *', '每天凌晨2点清理过期缓存', 1, NOW(), NOW()),
('清理临时文件', 'clear:temp', '0 3 * * 0', '每周日凌晨3点清理临时文件', 0, NOW(), NOW());
