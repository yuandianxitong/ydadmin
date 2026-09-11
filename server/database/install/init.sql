-- 元点Admin 初始数据（菜单、角色、部门、配置）
-- 按里程碑追加。不含管理员账号：安装后执行 php webman admin:init 建立超级管理员。

-- ---------------------------------------------------------------- M1a

-- 超级管理员角色：拥有 is_system=1 角色的管理员即超管（spec §4.2）
INSERT INTO `roles` (`id`, `name`, `title`, `description`, `data_scope`, `is_system`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (1, 'super_admin', '超级管理员', '系统超级管理员，拥有所有权限', 1, 1, 1, 0, NOW(), NOW());

-- 菜单：沿用 TP8 的 id（1 控制台，2 系统管理，10 管理员，20 角色，30 部门，50 菜单）
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (1, 0, 2, '控制台', 'Workbench', '/workbench', 'workbench/index', NULL, 'i-svg:gauge', NULL, 0, 1, 1, 0, NULL, 1, NULL, NULL, 1, 0, NOW(), NOW()),
  (2, 0, 1, '系统管理', 'System', '/system', 'LAYOUT', NULL, 'i-svg:settings', 'system', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 900, NOW(), NOW()),
  (10, 2, 2, '管理员管理', 'SystemAdmin', '/system/admin', '/system/admin/index', NULL, 'i-svg:user', 'system.admin.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (11, 10, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.admin.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (12, 10, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.admin.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (13, 10, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.admin.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (14, 10, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'system.admin.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (20, 2, 2, '角色管理', 'SystemRole', '/system/role', '/system/role/index', NULL, 'i-svg:users', 'system.role.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (21, 20, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.role.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (22, 20, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.role.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (23, 20, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.role.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (24, 20, 3, '授权', NULL, NULL, NULL, NULL, NULL, 'system.role.permission', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (25, 20, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'system.role.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (30, 2, 2, '部门管理', 'SystemDepartment', '/system/department', '/system/department/index', NULL, 'i-svg:network', 'system.department.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (31, 30, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.department.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (32, 30, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.department.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (33, 30, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.department.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (50, 2, 2, '菜单管理', 'SystemMenu', '/system/menu', '/system/menu/index', NULL, 'i-svg:layout-grid', 'system.menu.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (51, 50, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.menu.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (52, 50, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.menu.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (53, 50, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.menu.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());

INSERT INTO `departments` (`id`, `parent_id`, `name`, `code`, `leader`, `sort`, `status`, `created_at`, `updated_at`) VALUES
  (1, 0, '总公司', 'HQ', '管理员', 0, 1, NOW(), NOW()),
  (2, 1, '技术部', 'TECH', NULL, 1, 1, NOW(), NOW()),
  (3, 1, '市场部', 'MARKET', NULL, 2, 1, NOW(), NOW()),
  (4, 1, '财务部', 'FINANCE', NULL, 3, 1, NOW(), NOW()),
  (5, 2, '前端组', 'TECH-FE', NULL, 1, 1, NOW(), NOW()),
  (6, 2, '后端组', 'TECH-BE', NULL, 2, 1, NOW(), NOW());

-- basic 分组：含登录安全四项（验证码开、失败 5 次、锁 30 分钟、密码至少 6 位），M1 起全部生效。
-- is_public=1：这 18 项都不是凭据、且前端要用，出现在 config/global；
-- 凭据类键名另有黑名单兜底（password_min_length 因含 password 仍被排除，前端不从 global 读它）
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `is_public`, `created_at`, `updated_at`) VALUES
  ('site_name', '元点Admin', 'basic', 'string', '网站名称', '显示在浏览器标题栏和系统Logo旁', NULL, NULL, 1, 1, 1, NOW(), NOW()),
  ('site_url', 'http://localhost', 'basic', 'string', '网站地址', '网站访问地址，用于生成完整链接', NULL, NULL, 2, 1, 1, NOW(), NOW()),
  ('site_logo', '/storage/uploads/images/logo.png', 'basic', 'file', '网站Logo', '建议尺寸 200x50，支持 PNG/SVG 格式', NULL, NULL, 3, 1, 1, NOW(), NOW()),
  ('site_favicon', '/storage/uploads/images/favicon.ico', 'basic', 'file', '网站图标', '浏览器标签页图标，建议 32x32 ICO/PNG 格式', NULL, NULL, 4, 1, 1, NOW(), NOW()),
  ('site_description', '一款通用的后台管理系统', 'basic', 'string', '网站描述', '用于SEO和网站简介', NULL, NULL, 5, 1, 1, NOW(), NOW()),
  ('site_keywords', '后台管理,管理系统,Admin', 'basic', 'string', 'SEO关键词', '多个关键词用英文逗号分隔', NULL, NULL, 6, 1, 1, NOW(), NOW()),
  ('site_icp', '', 'basic', 'string', 'ICP备案号', '如：京ICP备XXXXXXXX号', NULL, NULL, 7, 1, 1, NOW(), NOW()),
  ('site_copyright', 'Copyright © 2024 Dev007. All rights reserved.', 'basic', 'string', '版权信息', '显示在页面底部的版权声明', NULL, NULL, 8, 1, 1, NOW(), NOW()),
  ('site_phone', '', 'basic', 'string', '联系电话', '网站管理员联系电话', NULL, NULL, 9, 1, 1, NOW(), NOW()),
  ('site_email', '', 'basic', 'string', '联系邮箱', '网站管理员联系邮箱', NULL, NULL, 10, 1, 1, NOW(), NOW()),
  ('site_address', '', 'basic', 'string', '联系地址', '公司或团队地址', NULL, NULL, 11, 1, 1, NOW(), NOW()),
  ('site_status', '1', 'basic', 'boolean', '网站开关', '关闭后前台将显示维护提示', NULL, NULL, 12, 1, 1, NOW(), NOW()),
  ('site_close_tip', '网站维护中，请稍后再试...', 'basic', 'string', '关闭提示', '网站关闭时显示的提示信息', NULL, NULL, 13, 1, 1, NOW(), NOW()),
  ('user_register', '1', 'basic', 'boolean', '开放注册', '是否允许新用户注册', NULL, NULL, 14, 1, 1, NOW(), NOW()),
  ('login_captcha', '1', 'basic', 'boolean', '登录验证码', '登录时是否需要输入验证码', NULL, NULL, 15, 1, 1, NOW(), NOW()),
  ('password_min_length', '6', 'basic', 'number', '密码最小长度', '用户密码最少字符数', NULL, NULL, 16, 1, 1, NOW(), NOW()),
  ('login_max_retry', '5', 'basic', 'number', '登录失败上限', '连续登录失败后锁定账号的次数', NULL, NULL, 17, 1, 1, NOW(), NOW()),
  ('login_lock_duration', '30', 'basic', 'number', '锁定时长(分钟)', '账号被锁定后的等待时间', NULL, NULL, 18, 1, 1, NOW(), NOW());

-- ---------------------------------------------------------------- M1b：系统配置

-- 菜单：沿用 TP8 的 id（100 系统配置，101 编辑按钮）
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (100, 2, 2, '系统配置', 'SystemConfig', '/system/config', '/system/config/index', NULL, 'i-svg:cog', 'system.config.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 10, NOW(), NOW()),
  (101, 100, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.config.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW());
