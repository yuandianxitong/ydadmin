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
    (76, 'channel.miniapp.config', '小程序配置', '渠道管理', '小程序配置管理', 'admin', 1, 76, NOW(), NOW()),
    -- 协议管理
    (80, 'agreement', '协议管理', '内容管理', '协议管理权限', 'admin', 1, 80, NOW(), NOW()),
    (81, 'agreement.list', '协议列表', '内容管理', '查看协议列表', 'admin', 1, 81, NOW(), NOW()),
    (82, 'agreement.detail', '协议详情', '内容管理', '查看协议详情', 'admin', 1, 82, NOW(), NOW()),
    (83, 'agreement.create', '创建协议', '内容管理', '创建协议', 'admin', 1, 83, NOW(), NOW()),
    (84, 'agreement.update', '编辑协议', '内容管理', '编辑协议', 'admin', 1, 84, NOW(), NOW()),
    (85, 'agreement.delete', '删除协议', '内容管理', '删除协议', 'admin', 1, 85, NOW(), NOW()),
    -- 公告管理
    (86, 'announcement', '公告管理', '内容管理', '公告管理权限', 'admin', 1, 86, NOW(), NOW()),
    (87, 'announcement.list', '公告列表', '内容管理', '查看公告列表', 'admin', 1, 87, NOW(), NOW()),
    (88, 'announcement.detail', '公告详情', '内容管理', '查看公告详情', 'admin', 1, 88, NOW(), NOW()),
    (89, 'announcement.create', '创建公告', '内容管理', '创建公告', 'admin', 1, 89, NOW(), NOW()),
    (90, 'announcement.update', '编辑公告', '内容管理', '编辑公告', 'admin', 1, 90, NOW(), NOW()),
    (91, 'announcement.status', '公告状态', '内容管理', '修改公告状态', 'admin', 1, 91, NOW(), NOW()),
    (92, 'announcement.delete', '删除公告', '内容管理', '删除公告', 'admin', 1, 92, NOW(), NOW()),
    -- 反馈管理
    (93, 'feedback', '反馈管理', '内容管理', '反馈管理权限', 'admin', 1, 93, NOW(), NOW()),
    (94, 'feedback.list', '反馈列表', '内容管理', '查看反馈列表', 'admin', 1, 94, NOW(), NOW()),
    (95, 'feedback.detail', '反馈详情', '内容管理', '查看反馈详情', 'admin', 1, 95, NOW(), NOW()),
    (96, 'feedback.reply', '回复反馈', '内容管理', '回复用户反馈', 'admin', 1, 96, NOW(), NOW()),
    (97, 'feedback.close', '关闭反馈', '内容管理', '关闭反馈', 'admin', 1, 97, NOW(), NOW()),
    (98, 'feedback.delete', '删除反馈', '内容管理', '删除反馈', 'admin', 1, 98, NOW(), NOW()),
    -- 区域管理
    (100, 'region', '区域管理', '应用管理', '区域管理权限', 'admin', 1, 100, NOW(), NOW()),
    (101, 'region.list', '区域列表', '应用管理', '查看区域列表', 'admin', 1, 101, NOW(), NOW()),
    (102, 'region.detail', '区域详情', '应用管理', '查看区域详情', 'admin', 1, 102, NOW(), NOW()),
    (103, 'region.create', '创建区域', '应用管理', '创建区域', 'admin', 1, 103, NOW(), NOW()),
    (104, 'region.update', '编辑区域', '应用管理', '编辑区域', 'admin', 1, 104, NOW(), NOW()),
    (105, 'region.delete', '删除区域', '应用管理', '删除区域', 'admin', 1, 105, NOW(), NOW()),
    -- 应用版本
    (106, 'version', '应用版本', '应用管理', '应用版本管理权限', 'admin', 1, 106, NOW(), NOW()),
    (107, 'version.list', '版本列表', '应用管理', '查看版本列表', 'admin', 1, 107, NOW(), NOW()),
    (108, 'version.detail', '版本详情', '应用管理', '查看版本详情', 'admin', 1, 108, NOW(), NOW()),
    (109, 'version.create', '创建版本', '应用管理', '创建版本', 'admin', 1, 109, NOW(), NOW()),
    (110, 'version.update', '编辑版本', '应用管理', '编辑版本', 'admin', 1, 110, NOW(), NOW()),
    (111, 'version.delete', '删除版本', '应用管理', '删除版本', 'admin', 1, 111, NOW(), NOW()),
    -- 文章栏目
    (130, 'article_category', '文章栏目管理', '内容管理', '文章栏目管理权限', 'admin', 1, 130, NOW(), NOW()),
    (131, 'article_category.list', '栏目列表', '内容管理', '查看文章栏目列表', 'admin', 1, 131, NOW(), NOW()),
    (132, 'article_category.create', '创建栏目', '内容管理', '创建文章栏目', 'admin', 1, 132, NOW(), NOW()),
    (133, 'article_category.update', '编辑栏目', '内容管理', '编辑文章栏目', 'admin', 1, 133, NOW(), NOW()),
    (134, 'article_category.delete', '删除栏目', '内容管理', '删除文章栏目', 'admin', 1, 134, NOW(), NOW()),
    -- 文章管理
    (140, 'article', '文章管理', '内容管理', '文章管理权限', 'admin', 1, 140, NOW(), NOW()),
    (141, 'article.list', '文章列表', '内容管理', '查看文章列表', 'admin', 1, 141, NOW(), NOW()),
    (142, 'article.detail', '文章详情', '内容管理', '查看文章详情', 'admin', 1, 142, NOW(), NOW()),
    (143, 'article.create', '创建文章', '内容管理', '创建新文章', 'admin', 1, 143, NOW(), NOW()),
    (144, 'article.update', '编辑文章', '内容管理', '编辑文章', 'admin', 1, 144, NOW(), NOW()),
    (145, 'article.delete', '删除文章', '内容管理', '删除文章', 'admin', 1, 145, NOW(), NOW()),
    (146, 'article.status', '文章状态', '内容管理', '发布/下架文章', 'admin', 1, 146, NOW(), NOW()),
    -- 用户管理
    (150, 'user', '用户管理', '用户管理', '用户管理权限', 'admin', 1, 150, NOW(), NOW()),
    (151, 'user.list', '用户列表', '用户管理', '查看用户列表', 'admin', 1, 151, NOW(), NOW()),
    (152, 'user.detail', '用户详情', '用户管理', '查看用户详情', 'admin', 1, 152, NOW(), NOW()),
    (153, 'user.adjust-balance', '调整余额', '用户管理', '调整用户余额', 'admin', 1, 153, NOW(), NOW()),
    (154, 'user.adjust-points', '调整积分', '用户管理', '调整用户积分', 'admin', 1, 154, NOW(), NOW()),
    (155, 'user.status', '用户状态', '用户管理', '启用/禁用用户', 'admin', 1, 155, NOW(), NOW()),
    (156, 'user.balance-logs', '余额记录', '用户管理', '查看余额记录', 'admin', 1, 156, NOW(), NOW()),
    (157, 'user.points-logs', '积分记录', '用户管理', '查看积分记录', 'admin', 1, 157, NOW(), NOW()),
    -- 开放平台
    (160, 'channel.open', '开放平台', '渠道管理', '开放平台管理权限', 'admin', 1, 160, NOW(), NOW()),
    (161, 'channel.open.config', '开放平台配置', '渠道管理', '开放平台配置管理', 'admin', 1, 161, NOW(), NOW());

-- ============================================================
-- 菜单数据
-- ============================================================
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  -- 控制台
  (1, 0, 2, '控制台', 'Workbench', '/workbench', 'workbench/index', NULL, 'i-svg:gauge', NULL, 0, 1, 1, 0, NULL, 1, NULL, NULL, 1, 0, NOW(), NOW()),

  -- ===== 系统管理 =====
  (2, 0, 1, '系统管理', 'System', '/system', 'LAYOUT', NULL, 'i-svg:settings', 'system', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 900, NOW(), NOW()),

  -- 管理员管理
  (10, 2, 2, '管理员管理', 'SystemAdmin', '/system/admin', '/system/admin/index', NULL, 'i-svg:user', 'system.admin', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (11, 10, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.admin.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (12, 10, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.admin.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (13, 10, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.admin.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 角色管理
  (20, 2, 2, '角色管理', 'SystemRole', '/system/role', '/system/role/index', NULL, 'i-svg:users', 'system.role', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (21, 20, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.role.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (22, 20, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.role.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (23, 20, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.role.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (24, 20, 3, '授权', NULL, NULL, NULL, NULL, NULL, 'system.role.permission', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- 部门管理
  (30, 2, 2, '部门管理', 'SystemDepartment', '/system/department', '/system/department/index', NULL, 'i-svg:network', 'system.department', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (31, 30, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.department.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (32, 30, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.department.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (33, 30, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.department.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 权限管理
  (40, 2, 2, '权限管理', 'SystemPermission', '/system/permission', '/system/permission/index', NULL, 'i-svg:lock', 'system.permission', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- 菜单管理
  (50, 2, 2, '菜单管理', 'SystemMenu', '/system/menu', '/system/menu/index', NULL, 'i-svg:layout-grid', 'system.menu', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (51, 50, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.menu.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (52, 50, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.menu.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (53, 50, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.menu.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 数据字典
  (60, 2, 2, '数据字典', 'SystemDictionary', '/system/dictionary', '/system/dictionary/index', NULL, 'i-svg:library-big', 'system.dictionary', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 6, NOW(), NOW()),
  (61, 60, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (62, 60, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (63, 60, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 文件管理
  (70, 2, 2, '文件管理', 'SystemFile', '/system/file', '/system/file/index', NULL, 'i-svg:folder-open', 'system.file', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 7, NOW(), NOW()),
  (71, 70, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.file.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- 通知管理
  (80, 2, 2, '通知管理', 'SystemNotification', '/system/notification', '/system/notification/index', NULL, 'i-svg:bell', 'system.notification', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 8, NOW(), NOW()),
  (81, 80, 3, '发布', NULL, NULL, NULL, NULL, NULL, 'system.notification.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (82, 80, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.notification.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (83, 80, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.notification.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 定时任务
  (90, 2, 2, '定时任务', 'SystemCronJob', '/system/cron-job', '/system/cron-job/index', NULL, 'i-svg:bolt', 'system.cron_job', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 9, NOW(), NOW()),
  (91, 90, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (92, 90, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (93, 90, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (94, 90, 3, '执行', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.run', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- 系统配置
  (100, 2, 2, '系统配置', 'SystemConfig', '/system/config', '/system/config/index', NULL, 'i-svg:cog', 'system.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 10, NOW(), NOW()),

  -- 日志管理（目录）
  (110, 2, 1, '日志管理', 'SystemLog', '/system/log', 'LAYOUT', NULL, 'i-svg:scroll-text', 'system.log', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 11, NOW(), NOW()),
  (111, 110, 2, '登录日志', 'SystemLoginLog', '/system/log/login', '/system/log/login', NULL, NULL, 'system.log.login', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (112, 110, 2, '操作日志', 'SystemOperationLog', '/system/log/operation', '/system/log/operation', NULL, NULL, 'system.log.operation', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),

  -- ===== 开发工具 =====
  (3, 0, 1, '开发工具', 'DevTools', '/dev-tools', 'LAYOUT', NULL, 'i-svg:cpu', NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 950, NOW(), NOW()),

  -- 代码生成器
  (200, 3, 2, '代码生成器', 'DevGenerator', '/dev-tools/generator', '/system/generator/index', NULL, 'i-svg:file-sliders', 'system.generator', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- API文档
  (210, 3, 2, 'API文档', 'DevApiDoc', '/dev-tools/api-doc', '/system/api-doc/index', NULL, 'i-svg:notebook-text', 'system.api_doc', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),

  -- ===== 消息管理（系统管理子模块） =====
  (120, 2, 1, '消息管理', 'SystemMessage', '/system/message', 'LAYOUT', '/system/message/template', 'i-svg:message-circle-more', 'system.message', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 12, NOW(), NOW()),
  (121, 120, 2, '消息模板', 'SystemMessageTemplate', '/system/message/template', '/system/message/template/index', NULL, 'el-icon-Tickets', 'system.message.template', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (122, 120, 2, '发送记录', 'SystemMessageLog', '/system/message/log', '/system/message/log/index', NULL, 'el-icon-List', 'system.message.log', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),

  -- ===== 渠道管理 =====
  (4, 0, 1, '渠道管理', 'Channel', '/channel', 'LAYOUT', '/channel/official/config', 'i-svg:send', 'channel', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 700, NOW(), NOW()),

  -- 公众号（目录）
  (5, 4, 1, '公众号', 'ChannelOfficial', '/channel/official', 'LAYOUT', '/channel/official/config', 'i-svg:compass', 'channel.official', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (400, 5, 2, '公众号配置', 'ChannelOfficialConfig', '/channel/official/config', '/channel/official/config', NULL, 'el-icon-Setting', 'channel.official.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (410, 5, 2, '自定义菜单', 'ChannelOfficialMenu', '/channel/official/menu', '/channel/official/menu', NULL, 'el-icon-Grid', 'channel.official.menu', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (420, 5, 2, '自动回复', 'ChannelAutoReply', '/channel/official/auto-reply', '/channel/official/auto-reply', NULL, 'el-icon-ChatSquare', 'channel.official.auto_reply', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 小程序（目录）
  (6, 4, 1, '小程序', 'ChannelMiniApp', '/channel/miniapp', 'LAYOUT', '/channel/miniapp/config', 'i-svg:smartphone', 'channel.miniapp', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (500, 6, 2, '小程序配置', 'ChannelMiniAppConfig', '/channel/miniapp/config', '/channel/miniapp/config', NULL, 'el-icon-Setting', 'channel.miniapp.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- ===== 内容管理 =====
  (7, 0, 1, '内容管理', 'Content', '/content', 'LAYOUT', '/content/agreement', 'i-svg:newspaper', 'agreement.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 600, NOW(), NOW()),
  (700, 7, 2, '协议管理', 'ContentAgreement', '/content/agreement', '/content/agreement/index', NULL, 'i-svg:file-text', 'agreement.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (701, 700, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'agreement.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (702, 700, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'agreement.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (703, 700, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'agreement.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (710, 7, 2, '公告管理', 'ContentAnnouncement', '/content/announcement', '/content/announcement/index', NULL, 'i-svg:bell-ring', 'announcement.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (711, 710, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'announcement.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (712, 710, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'announcement.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (713, 710, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'announcement.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (720, 7, 2, '反馈管理', 'ContentFeedback', '/content/feedback', '/content/feedback/index', NULL, 'i-svg:message-square-text', 'feedback.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (721, 720, 3, '回复', NULL, NULL, NULL, NULL, NULL, 'feedback.reply', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (722, 720, 3, '关闭', NULL, NULL, NULL, NULL, NULL, 'feedback.close', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (723, 720, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'feedback.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  -- 文章资讯（目录）
  (725, 7, 1, '文章资讯', 'ContentArticleGroup', '/content/article-group', 'LAYOUT', '/content/article-category', 'i-svg:newspaper', 'article_category.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  -- 文章栏目
  (730, 725, 2, '文章栏目', 'ContentArticleCategory', '/content/article-category', '/content/article-category/index', NULL, 'i-svg:tag', 'article_category.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (731, 730, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'article_category.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (732, 730, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'article_category.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (733, 730, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'article_category.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  -- 文章管理
  (740, 725, 2, '文章管理', 'ContentArticle', '/content/article', '/content/article/index', NULL, 'i-svg:file-text', 'article.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (741, 740, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'article.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (742, 740, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'article.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (743, 740, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'article.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (744, 740, 3, '发布/下架', NULL, NULL, NULL, NULL, NULL, 'article.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- ===== 应用管理 =====
  (8, 0, 1, '应用管理', 'Application', '/app', 'LAYOUT', '/app/region', 'i-svg:box', 'region.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 650, NOW(), NOW()),
  (800, 8, 2, '区域管理', 'AppRegion', '/app/region', '/content/region/index', NULL, 'i-svg:map-pinned', 'region.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (801, 800, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'region.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (802, 800, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'region.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (803, 800, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'region.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (810, 8, 2, '应用版本', 'AppVersion', '/app/version', '/content/version/index', NULL, 'i-svg:arrow-up-from-line', 'version.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (811, 810, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'version.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (812, 810, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'version.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (813, 810, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'version.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- ===== 用户管理 =====
  (9, 0, 1, '用户管理', 'User', '/user', 'LAYOUT', '/user/user', 'i-svg:users', 'user', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 500, NOW(), NOW()),
  -- 用户列表
  (900, 9, 2, '用户列表', 'UserList', '/user/user', '/user/user/index', NULL, 'i-svg:user', 'user.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (901, 900, 3, '查看详情', NULL, NULL, NULL, NULL, NULL, 'user.detail', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (902, 900, 3, '调整余额', NULL, NULL, NULL, NULL, NULL, 'user.adjust-balance', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (903, 900, 3, '调整积分', NULL, NULL, NULL, NULL, NULL, 'user.adjust-points', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (904, 900, 3, '更新状态', NULL, NULL, NULL, NULL, NULL, 'user.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  -- 余额记录
  (910, 9, 2, '余额记录', 'UserBalanceLog', '/user/balance-log', '/user/balance-log/index', NULL, 'i-svg:wallet', 'user.balance-logs', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  -- 积分记录
  (920, 9, 2, '积分记录', 'UserPointsLog', '/user/points-log', '/user/points-log/index', NULL, 'i-svg:star', 'user.points-logs', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- ===== 开放平台（渠道管理子菜单） =====
  (11, 4, 1, '开放平台', 'ChannelOpen', '/channel/open', 'LAYOUT', '/channel/open/config', 'i-svg:globe', 'channel.open', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (550, 11, 2, '开放平台配置', 'ChannelOpenConfig', '/channel/open/config', '/channel/open/config', NULL, 'el-icon-Setting', 'channel.open.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW());

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

-- ============================================================
-- 系统配置种子数据
-- ============================================================

INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES

-- ===== 基础配置 (basic) =====
('site_name', '元点Admin', 'basic', 'string', '网站名称', '显示在浏览器标题栏和系统Logo旁', NULL, NULL, 1, 1, NOW(), NOW()),
('site_url', 'http://localhost', 'basic', 'string', '网站地址', '网站访问地址，用于生成完整链接', NULL, NULL, 2, 1, NOW(), NOW()),
('site_logo', '/storage/uploads/images/logo.png', 'basic', 'file', '网站Logo', '建议尺寸 200x50，支持 PNG/SVG 格式', NULL, NULL, 3, 1, NOW(), NOW()),
('site_favicon', '/storage/uploads/images/favicon.ico', 'basic', 'file', '网站图标', '浏览器标签页图标，建议 32x32 ICO/PNG 格式', NULL, NULL, 4, 1, NOW(), NOW()),
('site_description', '一款通用的后台管理系统', 'basic', 'string', '网站描述', '用于SEO和网站简介', NULL, NULL, 5, 1, NOW(), NOW()),
('site_keywords', '后台管理,管理系统,Admin', 'basic', 'string', 'SEO关键词', '多个关键词用英文逗号分隔', NULL, NULL, 6, 1, NOW(), NOW()),
('site_icp', '', 'basic', 'string', 'ICP备案号', '如：京ICP备XXXXXXXX号', NULL, NULL, 7, 1, NOW(), NOW()),
('site_copyright', 'Copyright © 2024 Dev007. All rights reserved.', 'basic', 'string', '版权信息', '显示在页面底部的版权声明', NULL, NULL, 8, 1, NOW(), NOW()),
('site_phone', '', 'basic', 'string', '联系电话', '网站管理员联系电话', NULL, NULL, 9, 1, NOW(), NOW()),
('site_email', '', 'basic', 'string', '联系邮箱', '网站管理员联系邮箱', NULL, NULL, 10, 1, NOW(), NOW()),
('site_address', '', 'basic', 'string', '联系地址', '公司或团队地址', NULL, NULL, 11, 1, NOW(), NOW()),
('site_status', '1', 'basic', 'boolean', '网站开关', '关闭后前台将显示维护提示', NULL, NULL, 12, 1, NOW(), NOW()),
('site_close_tip', '网站维护中，请稍后再试...', 'basic', 'string', '关闭提示', '网站关闭时显示的提示信息', NULL, NULL, 13, 1, NOW(), NOW()),
('user_register', '1', 'basic', 'boolean', '开放注册', '是否允许新用户注册', NULL, NULL, 14, 1, NOW(), NOW()),
('login_captcha', '1', 'basic', 'boolean', '登录验证码', '登录时是否需要输入验证码', NULL, NULL, 15, 1, NOW(), NOW()),
('password_min_length', '6', 'basic', 'number', '密码最小长度', '用户密码最少字符数', NULL, NULL, 16, 1, NOW(), NOW()),
('login_max_retry', '5', 'basic', 'number', '登录失败上限', '连续登录失败后锁定账号的次数', NULL, NULL, 17, 1, NOW(), NOW()),
('login_lock_duration', '30', 'basic', 'number', '锁定时长(分钟)', '账号被锁定后的等待时间', NULL, NULL, 18, 1, NOW(), NOW()),

-- ===== 邮件配置 (email) =====
('smtp_host', '', 'email', 'string', 'SMTP服务器', '例如：smtp.qq.com、smtp.163.com', NULL, NULL, 1, 1, NOW(), NOW()),
('smtp_port', '465', 'email', 'number', 'SMTP端口', '常用端口：25(不加密)、465(SSL)、587(TLS)', NULL, NULL, 2, 1, NOW(), NOW()),
('smtp_user', '', 'email', 'string', 'SMTP用户名', '通常为发件人邮箱地址', NULL, NULL, 3, 1, NOW(), NOW()),
('smtp_pass', '', 'email', 'string', 'SMTP密码', 'SMTP授权码或密码', NULL, NULL, 4, 1, NOW(), NOW()),
('smtp_from_address', '', 'email', 'string', '发件人地址', '发件人邮箱地址', NULL, NULL, 5, 1, NOW(), NOW()),
('smtp_from_name', '元点Admin', 'email', 'string', '发件人名称', '收件人看到的发件人名称', NULL, NULL, 6, 1, NOW(), NOW()),
('smtp_encryption', 'ssl', 'email', 'select', '加密方式', '邮件传输加密方式', '{"ssl":"SSL","tls":"TLS","none":"不加密"}', NULL, 7, 1, NOW(), NOW()),
('email_test_address', '', 'email', 'string', '测试收件地址', '用于发送测试邮件的收件人地址', NULL, NULL, 8, 1, NOW(), NOW()),

-- ===== 短信配置 (sms) =====
('sms_driver', 'aliyun', 'sms', 'select', '短信服务商', '选择短信发送服务商', '{"aliyun":"阿里云","tencent":"腾讯云"}', NULL, 1, 1, NOW(), NOW()),
('sms_access_key', '', 'sms', 'string', 'AccessKey ID', '短信服务商提供的 AccessKey ID', NULL, NULL, 2, 1, NOW(), NOW()),
('sms_access_secret', '', 'sms', 'string', 'AccessKey Secret', '短信服务商提供的 AccessKey Secret', NULL, NULL, 3, 1, NOW(), NOW()),
('sms_sign_name', '', 'sms', 'string', '短信签名', '已审核通过的短信签名', NULL, NULL, 4, 1, NOW(), NOW()),
('sms_template_code', '', 'sms', 'string', '验证码模板', '短信验证码模板编号', NULL, NULL, 5, 1, NOW(), NOW()),
('sms_template_notify', '', 'sms', 'string', '通知模板', '短信通知模板编号', NULL, NULL, 6, 1, NOW(), NOW()),

-- ===== 支付配置 (payment) =====
('pay_alipay_enabled', '0', 'payment', 'boolean', '启用支付宝', '是否开启支付宝支付', NULL, NULL, 1, 1, NOW(), NOW()),
('pay_alipay_app_id', '', 'payment', 'string', '支付宝AppID', '支付宝开放平台应用AppID', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 2, 1, NOW(), NOW()),
('pay_alipay_private_key', '', 'payment', 'string', '应用私钥', '支付宝应用私钥(RSA2)', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 3, 1, NOW(), NOW()),
('pay_alipay_public_key', '', 'payment', 'string', '支付宝公钥', '支付宝公钥', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 4, 1, NOW(), NOW()),
('pay_alipay_notify_url', '', 'payment', 'string', '异步通知地址', '支付宝异步回调通知URL', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 5, 1, NOW(), NOW()),
('pay_wechat_enabled', '0', 'payment', 'boolean', '启用微信支付', '是否开启微信支付', NULL, NULL, 6, 1, NOW(), NOW()),
('pay_wechat_app_id', '', 'payment', 'string', '微信AppID', '微信公众号或小程序AppID', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 7, 1, NOW(), NOW()),
('pay_wechat_mch_id', '', 'payment', 'string', '微信商户号', '微信支付商户号', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 8, 1, NOW(), NOW()),
('pay_wechat_api_key', '', 'payment', 'string', '微信API密钥', '微信支付APIv3密钥', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 9, 1, NOW(), NOW()),
('pay_wechat_api_v3_key', '', 'payment', 'string', '微信APIv3密钥', '微信支付APIv3密钥（用于V3接口）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 10, 1, NOW(), NOW()),
('pay_wechat_serial_no', '', 'payment', 'string', '微信证书序列号', '微信支付平台证书序列号', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 11, 1, NOW(), NOW()),
('pay_wechat_private_key_path', '', 'payment', 'string', '微信私钥文件', '商户API私钥文件路径（apiclient_key.pem）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 12, 1, NOW(), NOW()),
('pay_wechat_cert_path', '', 'payment', 'string', '微信证书文件', '商户API证书文件路径（apiclient_cert.pem）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 13, 1, NOW(), NOW()),
('pay_wechat_notify_url', '', 'payment', 'string', '异步通知地址', '微信支付异步回调通知URL', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 14, 1, NOW(), NOW()),

-- ===== 存储配置 (storage) =====
('storage_driver', 'local', 'storage', 'select', '存储方式', '选择文件存储方式', '{"local":"本地存储","aliyun":"阿里云OSS","tencent":"腾讯云COS","qiniu":"七牛云"}', NULL, 1, 1, NOW(), NOW()),
('storage_upload_max_size', '10', 'storage', 'number', '最大上传(MB)', '单个文件最大上传大小，单位MB', NULL, NULL, 2, 1, NOW(), NOW()),
('storage_upload_allowed_ext', 'jpg,jpeg,png,gif,svg,webp,bmp,doc,docx,xls,xlsx,ppt,pptx,pdf,zip,rar,txt,csv', 'storage', 'string', '允许的文件类型', '允许上传的文件扩展名，英文逗号分隔', NULL, NULL, 3, 1, NOW(), NOW()),
('storage_image_max_size', '5', 'storage', 'number', '图片最大(MB)', '单张图片最大上传大小，单位MB', NULL, NULL, 4, 1, NOW(), NOW()),
-- 阿里云 OSS
('storage_oss_access_key', '', 'storage', 'string', 'OSS AccessKey', '阿里云OSS AccessKey ID', NULL, '{"field":"storage_driver","value":"aliyun"}', 10, 1, NOW(), NOW()),
('storage_oss_access_secret', '', 'storage', 'string', 'OSS AccessSecret', '阿里云OSS AccessKey Secret', NULL, '{"field":"storage_driver","value":"aliyun"}', 11, 1, NOW(), NOW()),
('storage_oss_bucket', '', 'storage', 'string', 'OSS Bucket', '阿里云OSS Bucket名称', NULL, '{"field":"storage_driver","value":"aliyun"}', 12, 1, NOW(), NOW()),
('storage_oss_endpoint', '', 'storage', 'string', 'OSS Endpoint', '阿里云OSS 访问域名，如 oss-cn-hangzhou.aliyuncs.com', NULL, '{"field":"storage_driver","value":"aliyun"}', 13, 1, NOW(), NOW()),
('storage_oss_domain', '', 'storage', 'string', 'OSS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"aliyun"}', 14, 1, NOW(), NOW()),
-- 腾讯云 COS
('storage_cos_secret_id', '', 'storage', 'string', 'COS SecretId', '腾讯云COS SecretId', NULL, '{"field":"storage_driver","value":"tencent"}', 20, 1, NOW(), NOW()),
('storage_cos_secret_key', '', 'storage', 'string', 'COS SecretKey', '腾讯云COS SecretKey', NULL, '{"field":"storage_driver","value":"tencent"}', 21, 1, NOW(), NOW()),
('storage_cos_bucket', '', 'storage', 'string', 'COS Bucket', '腾讯云COS Bucket名称（含AppId后缀，如 bucket-1250000000）', NULL, '{"field":"storage_driver","value":"tencent"}', 22, 1, NOW(), NOW()),
('storage_cos_region', '', 'storage', 'string', 'COS Region', '腾讯云COS 地域，如 ap-guangzhou', NULL, '{"field":"storage_driver","value":"tencent"}', 23, 1, NOW(), NOW()),
('storage_cos_domain', '', 'storage', 'string', 'COS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"tencent"}', 24, 1, NOW(), NOW()),
-- 七牛云
('storage_qiniu_access_key', '', 'storage', 'string', '七牛 AccessKey', '七牛云 AccessKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 30, 1, NOW(), NOW()),
('storage_qiniu_secret_key', '', 'storage', 'string', '七牛 SecretKey', '七牛云 SecretKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 31, 1, NOW(), NOW()),
('storage_qiniu_bucket', '', 'storage', 'string', '七牛 Bucket', '七牛云存储空间名称', NULL, '{"field":"storage_driver","value":"qiniu"}', 32, 1, NOW(), NOW()),
('storage_qiniu_domain', '', 'storage', 'string', '七牛访问域名', '七牛云存储空间绑定的域名（含协议，如 https://cdn.example.com）', NULL, '{"field":"storage_driver","value":"qiniu"}', 33, 1, NOW(), NOW()),

-- ===== 公众号配置 (wechat_official) =====
('wechat_official_name', '', 'wechat_official', 'string', '公众号名称', '微信公众号名称', NULL, NULL, 1, 1, NOW(), NOW()),
('wechat_official_original_id', '', 'wechat_official', 'string', '原始ID', '公众号原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, NOW(), NOW()),
('wechat_official_qrcode', '', 'wechat_official', 'file', '公众号二维码', '公众号二维码图片，建议 200x200', NULL, NULL, 3, 1, NOW(), NOW()),
('wechat_official_app_id', '', 'wechat_official', 'string', 'AppID', '微信公众号AppID（开发者ID）', NULL, NULL, 10, 1, NOW(), NOW()),
('wechat_official_app_secret', '', 'wechat_official', 'string', 'AppSecret', '微信公众号AppSecret（开发者密码）', NULL, NULL, 11, 1, NOW(), NOW()),
('wechat_official_token', '', 'wechat_official', 'string', 'Token', '微信公众号消息校验Token', NULL, NULL, 20, 1, NOW(), NOW()),
('wechat_official_aes_key', '', 'wechat_official', 'string', 'EncodingAESKey', '微信公众号消息加解密密钥（43位字符）', NULL, NULL, 21, 1, NOW(), NOW()),
('wechat_official_encrypt_type', '1', 'wechat_official', 'select', '消息加密方式', '1=明文模式 2=兼容模式 3=安全模式，需与微信后台保持一致', '{"1":"明文模式","2":"兼容模式","3":"安全模式"}', NULL, 22, 1, NOW(), NOW()),

-- ===== 小程序配置 (wechat_mini) =====
('wechat_mini_name', '', 'wechat_mini', 'string', '小程序名称', '微信小程序名称', NULL, NULL, 1, 1, NOW(), NOW()),
('wechat_mini_original_id', '', 'wechat_mini', 'string', '原始ID', '小程序原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, NOW(), NOW()),
('wechat_mini_qrcode', '', 'wechat_mini', 'file', '小程序二维码', '小程序二维码图片，建议 200x200', NULL, NULL, 3, 1, NOW(), NOW()),
('wechat_mini_app_id', '', 'wechat_mini', 'string', 'AppID', '微信小程序AppID', NULL, NULL, 10, 1, NOW(), NOW()),
('wechat_mini_app_secret', '', 'wechat_mini', 'string', 'AppSecret', '微信小程序AppSecret', NULL, NULL, 11, 1, NOW(), NOW()),
('wechat_mini_msg_token', '', 'wechat_mini', 'string', 'Token', '消息推送校验Token', NULL, NULL, 20, 1, NOW(), NOW()),
('wechat_mini_msg_aes_key', '', 'wechat_mini', 'string', 'EncodingAESKey', '消息推送加解密密钥（43位字符）', NULL, NULL, 21, 1, NOW(), NOW()),
('wechat_mini_msg_format', 'JSON', 'wechat_mini', 'select', '数据格式', '消息推送数据格式', '{"JSON":"JSON","XML":"XML"}', NULL, 22, 1, NOW(), NOW()),
('wechat_mini_encrypt_type', '1', 'wechat_mini', 'select', '消息加密方式', '1=明文模式 2=兼容模式 3=安全模式，需与微信后台保持一致', '{"1":"明文模式","2":"兼容模式","3":"安全模式"}', NULL, 23, 1, NOW(), NOW());

-- ===== 预置消息模板 =====
INSERT INTO `message_templates` (`name`, `code`, `sms_enabled`, `sms_template_id`, `sms_content`, `wechat_official_enabled`, `wechat_official_template_id`, `wechat_official_url`, `wechat_mini_enabled`, `wechat_mini_template_id`, `wechat_mini_page`, `variables`, `remark`, `status`, `created_at`, `updated_at`) VALUES
('登录验证码', 'login_captcha', 1, '', '您的登录验证码为${code}，${expire}分钟内有效，请勿泄露给他人。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"6789"},{"key":"expire","name":"有效期（分钟）","example":"5"}]', '用户登录时发送的验证码通知', 1, NOW(), NOW()),
('注册验证码', 'register_captcha', 1, '', '您的注册验证码为${code}，${expire}分钟内有效，请勿泄露给他人。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"1234"},{"key":"expire","name":"有效期（分钟）","example":"5"}]', '用户注册时发送的验证码通知', 1, NOW(), NOW()),
('找回密码', 'reset_password', 1, '', '您正在找回密码，验证码为${code}，${expire}分钟内有效。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"5678"},{"key":"expire","name":"有效期（分钟）","example":"5"}]', '用户找回密码时发送的验证码通知', 1, NOW(), NOW()),
('绑定手机', 'bind_mobile', 1, '', '您正在绑定手机号，验证码为${code}，${expire}分钟内有效。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"9012"},{"key":"expire","name":"有效期（分钟）","example":"5"}]', '用户绑定手机号时发送的验证码通知', 1, NOW(), NOW()),
('变更手机', 'change_mobile', 1, '', '您正在变更手机号，验证码为${code}，${expire}分钟内有效。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"3456"},{"key":"expire","name":"有效期（分钟）","example":"5"}]', '用户变更手机号时发送的验证码通知', 1, NOW(), NOW());

-- Banner 配置
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('banner_list', '[]', 'banner', 'json', '轮播图列表', '首页轮播图配置，JSON数组格式：[{"image":"图片地址","url":"跳转链接","title":"标题"}]', NULL, NULL, 1, 1, NOW(), NOW());
