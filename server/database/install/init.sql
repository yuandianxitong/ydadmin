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

-- ---------------------------------------------------------------- M1b：数据字典

-- 数据字典菜单（沿用 TP8 id 60–63）
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (60, 2, 2, '数据字典', 'SystemDictionary', '/system/dictionary', '/system/dictionary/index', NULL, 'i-svg:library-big', 'system.dictionary.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 6, NOW(), NOW()),
  (61, 60, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (62, 60, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (63, 60, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());

-- 示例字典（TP8 init.sql）：显式 id，字典项按 id 关联
INSERT INTO `dictionaries` (`id`, `name`, `code`, `description`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (1, '性别', 'gender', '用户性别', 1, 0, NOW(), NOW()),
  (2, '状态', 'common_status', '通用启用/禁用状态', 1, 1, NOW(), NOW());

INSERT INTO `dictionary_items` (`dictionary_id`, `label`, `value`, `tag_type`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (1, '男', '1', '', 1, 0, NOW(), NOW()),
  (1, '女', '2', '', 1, 1, NOW(), NOW()),
  (1, '未知', '0', 'info', 1, 2, NOW(), NOW()),
  (2, '启用', '1', 'success', 1, 0, NOW(), NOW()),
  (2, '禁用', '0', 'danger', 1, 1, NOW(), NOW());

-- ---------------------------------------------------------------- M1b：日志菜单

-- 日志管理：110 目录，111 登录日志，112 操作日志，113 删除，114 清空（沿用 TP8 id 与权限码）
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (110, 2, 1, '日志管理', 'SystemLog', '/system/log', 'LAYOUT', NULL, 'i-svg:scroll-text', 'system.log', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 11, NOW(), NOW()),
  (111, 110, 2, '登录日志', 'SystemLoginLog', '/system/log/login', '/system/log/login', NULL, NULL, 'system.log.login', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (112, 110, 2, '操作日志', 'SystemOperationLog', '/system/log/operation', '/system/log/operation', NULL, NULL, 'system.log.operation', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (113, 110, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.log.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (114, 110, 3, '清空', NULL, NULL, NULL, NULL, NULL, 'system.log.clear', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW());

-- ---------------------------------------------------------------- M1b：站内通知

-- 通知管理菜单（沿用 TP8 id 80–83）
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (80, 2, 2, '通知管理', 'SystemNotification', '/system/notification', '/system/notification/index', NULL, 'i-svg:bell', 'system.notification.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 8, NOW(), NOW()),
  (81, 80, 3, '发布', NULL, NULL, NULL, NULL, NULL, 'system.notification.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (82, 80, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.notification.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (83, 80, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.notification.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());

-- ---------------------------------------------------------------- M1c：文件管理菜单

-- 菜单：沿用 TP8 的 id（70 文件管理，72 编辑，71 删除）
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (70, 2, 2, '文件管理', 'SystemFile', '/system/file', '/system/file/index', NULL, 'i-svg:folder-open', 'system.file.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 7, NOW(), NOW()),
  (72, 70, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.file.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (71, 70, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.file.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW());

-- ---------------------------------------------------------------- M1c：存储配置

-- storage 分组 19 项：TP8 的 18 项（键名、值、说明、config_options、config_depends、sort_order 逐字照抄）
-- 加上本项目显式新增的 storage_oss_region（sort_order 15，排在 storage_oss_domain 之后，不打乱 TP8 的编号）。
-- is_public：只有前端真的要读的 7 个键公开——admin 的 app.store.ts 用 storage_oss_domain 拼图片前缀；
-- storage_upload_max_size / storage_image_max_size / storage_upload_allowed_ext 这三个上传限制键公开，
-- 是为了将来前端能做上传前的预校验，**目前 admin/src 里还没有任何地方读它们**（真正生效的校验在服务端
-- UploadService，前端不读也不影响正确性）。bucket / endpoint / region 与全部凭据一律不公开（凭据另有
-- SystemConfigService::isSensitiveKey() 黑名单兜底，红线 Test13 在守）。
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `is_public`, `created_at`, `updated_at`) VALUES
  ('storage_driver', 'local', 'storage', 'select', '存储方式', '选择文件存储方式', '{"local":"本地存储","aliyun":"阿里云OSS","tencent":"腾讯云COS","qiniu":"七牛云"}', NULL, 1, 1, 1, NOW(), NOW()),
  ('storage_upload_max_size', '10', 'storage', 'number', '最大上传(MB)', '单个文件最大上传大小，单位MB', NULL, NULL, 2, 1, 1, NOW(), NOW()),
  ('storage_upload_allowed_ext', 'jpg,jpeg,png,gif,svg,webp,bmp,doc,docx,xls,xlsx,ppt,pptx,pdf,zip,rar,txt,csv', 'storage', 'string', '允许的文件类型', '允许上传的文件扩展名，英文逗号分隔', NULL, NULL, 3, 1, 1, NOW(), NOW()),
  ('storage_image_max_size', '5', 'storage', 'number', '图片最大(MB)', '单张图片最大上传大小，单位MB', NULL, NULL, 4, 1, 1, NOW(), NOW()),
  -- 阿里云 OSS
  ('storage_oss_access_key', '', 'storage', 'string', 'OSS AccessKey', '阿里云OSS AccessKey ID', NULL, '{"field":"storage_driver","value":"aliyun"}', 10, 1, 0, NOW(), NOW()),
  ('storage_oss_access_secret', '', 'storage', 'string', 'OSS AccessSecret', '阿里云OSS AccessKey Secret', NULL, '{"field":"storage_driver","value":"aliyun"}', 11, 1, 0, NOW(), NOW()),
  ('storage_oss_bucket', '', 'storage', 'string', 'OSS Bucket', '阿里云OSS Bucket名称', NULL, '{"field":"storage_driver","value":"aliyun"}', 12, 1, 0, NOW(), NOW()),
  ('storage_oss_endpoint', '', 'storage', 'string', 'OSS Endpoint', '阿里云OSS 访问域名，如 oss-cn-hangzhou.aliyuncs.com', NULL, '{"field":"storage_driver","value":"aliyun"}', 13, 1, 0, NOW(), NOW()),
  ('storage_oss_domain', '', 'storage', 'string', 'OSS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"aliyun"}', 14, 1, 1, NOW(), NOW()),
  ('storage_oss_region', '', 'storage', 'string', 'OSS Region', '阿里云OSS 地域，如 cn-hangzhou；留空则尝试从 Endpoint 推导', NULL, '{"field":"storage_driver","value":"aliyun"}', 15, 1, 0, NOW(), NOW()),
  -- 腾讯云 COS
  ('storage_cos_secret_id', '', 'storage', 'string', 'COS SecretId', '腾讯云COS SecretId', NULL, '{"field":"storage_driver","value":"tencent"}', 20, 1, 0, NOW(), NOW()),
  ('storage_cos_secret_key', '', 'storage', 'string', 'COS SecretKey', '腾讯云COS SecretKey', NULL, '{"field":"storage_driver","value":"tencent"}', 21, 1, 0, NOW(), NOW()),
  ('storage_cos_bucket', '', 'storage', 'string', 'COS Bucket', '腾讯云COS Bucket名称（含AppId后缀，如 bucket-1250000000）', NULL, '{"field":"storage_driver","value":"tencent"}', 22, 1, 0, NOW(), NOW()),
  ('storage_cos_region', '', 'storage', 'string', 'COS Region', '腾讯云COS 地域，如 ap-guangzhou', NULL, '{"field":"storage_driver","value":"tencent"}', 23, 1, 0, NOW(), NOW()),
  ('storage_cos_domain', '', 'storage', 'string', 'COS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"tencent"}', 24, 1, 1, NOW(), NOW()),
  -- 七牛云
  ('storage_qiniu_access_key', '', 'storage', 'string', '七牛 AccessKey', '七牛云 AccessKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 30, 1, 0, NOW(), NOW()),
  ('storage_qiniu_secret_key', '', 'storage', 'string', '七牛 SecretKey', '七牛云 SecretKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 31, 1, 0, NOW(), NOW()),
  ('storage_qiniu_bucket', '', 'storage', 'string', '七牛 Bucket', '七牛云存储空间名称', NULL, '{"field":"storage_driver","value":"qiniu"}', 32, 1, 0, NOW(), NOW()),
  ('storage_qiniu_domain', '', 'storage', 'string', '七牛访问域名', '七牛云存储空间绑定的域名（含协议，如 https://cdn.example.com）', NULL, '{"field":"storage_driver","value":"qiniu"}', 33, 1, 1, NOW(), NOW());

-- ---------------------------------------------------------------- M2a：开发工具 / 代码生成器

-- 菜单：3 开发工具（顶级目录），200 代码生成器，201 生成按钮，210 API 文档（M2b）。
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (3, 0, 1, '开发工具', 'DevTools', '/dev-tools', 'LAYOUT', NULL, 'i-svg:cpu', NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 950, NOW(), NOW()),
  (200, 3, 2, '代码生成器', 'DevGenerator', '/dev-tools/generator', 'system/generator/index', NULL, 'i-svg:file-sliders', 'system.generator.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (201, 200, 3, '生成', NULL, NULL, NULL, NULL, NULL, 'system.generator.generate', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (210, 3, 2, 'API文档', 'DevApiDoc', '/dev-tools/api-doc', 'system/api-doc/index',
   NULL, 'i-svg:notebook-text', 'system.api_doc', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW());

-- ---------------------------------------------------------------- M3：定时任务

-- 菜单：沿用 TP8 id（90 定时任务，91–95 按钮）。TP8 漏种了 system.cron_job.clear 权限点，本版补齐
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (90, 2, 2, '定时任务', 'SystemCronJob', '/system/cron-job', '/system/cron-job/index', NULL, 'i-svg:bolt', 'system.cron_job.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 9, NOW(), NOW()),
  (91, 90, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (92, 90, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (93, 90, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (94, 90, 3, '执行', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.run', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (95, 90, 3, '清空日志', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.clear', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW());

-- 示例任务：每天凌晨 3 点清理 90 天前的管理员操作日志与登录日志（log:archive 见 app/command/LogArchiveCommand.php）
INSERT INTO `cron_jobs` (`name`, `command`, `expression`, `description`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  ('清理过期管理员日志', 'log:archive --days=90', '0 3 * * *', '每天凌晨 3 点清理 90 天前的管理员操作日志与登录日志', 1, 0, NOW(), NOW());

-- M5b：支付定时任务（spec §9）。命令必须同时在 config/cron.php 白名单里；payment:refund 永远不进这里。
INSERT INTO `cron_jobs` (`name`, `command`, `expression`, `description`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  ('支付订单超时关闭', 'payment:close-expired', '*/5 * * * *', '关闭超过支付时限的待支付订单；已支付的补记入账', 1, 0, NOW(), NOW()),
  ('退款结果对账', 'payment:reconcile-refunds', '*/10 * * * *', '查询处理中的退款并结算结果，失败的退款把余额加回', 1, 0, NOW(), NOW());

-- ---------------------------------------------------------------- M4：在线管理员

-- 菜单：120 在线管理员（系统管理下，排在日志管理 11 之后），121 强制下线按钮
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (120, 2, 2, '在线管理员', 'SystemOnline', '/system/online', '/system/online/index', NULL, 'i-svg:users-round', 'system.online.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 12, NOW(), NOW()),
  (121, 120, 3, '强制下线', NULL, NULL, NULL, NULL, NULL, 'system.online.logout', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW());

-- ---------------------------------------------------------------- M5a：短信配置

-- sms 分组 7 项（spec §7.1）。access_key/access_secret 两家共用（腾讯云那边叫 SecretId/SecretKey），
-- sdk_app_id 只有腾讯云用，所以给它挂 config_depends 让管理端按 sms_driver 联动显示（与 storage 分组同一写法）。
-- is_public 全部为 0：短信配置里没有任何一项是前端要读的；凭据类键名另有
-- SystemConfigService::isSensitiveKey() 黑名单兜底（红线 Test13 在守）。
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `is_public`, `created_at`, `updated_at`) VALUES
  ('sms_driver', 'aliyun', 'sms', 'select', '短信服务商', '选择短信发送服务商', '{"aliyun":"阿里云","tencent":"腾讯云"}', NULL, 1, 1, 0, NOW(), NOW()),
  ('sms_access_key', '', 'sms', 'string', 'AccessKey', '阿里云 AccessKey ID / 腾讯云 SecretId', NULL, NULL, 2, 1, 0, NOW(), NOW()),
  ('sms_access_secret', '', 'sms', 'string', 'AccessSecret', '阿里云 AccessKey Secret / 腾讯云 SecretKey', NULL, NULL, 3, 1, 0, NOW(), NOW()),
  ('sms_sign_name', '', 'sms', 'string', '短信签名', '已在服务商控制台审核通过的签名，如「元点科技」', NULL, NULL, 4, 1, 0, NOW(), NOW()),
  ('sms_sdk_app_id', '', 'sms', 'string', '短信应用ID', '腾讯云短信应用 SdkAppId（阿里云不需要）', NULL, '{"field":"sms_driver","value":"tencent"}', 5, 1, 0, NOW(), NOW()),
  ('sms_template_login', '', 'sms', 'string', '登录模板ID', '登录 / 短信登录场景的模板 id（阿里云 TemplateCode，腾讯云 TemplateId）', NULL, NULL, 10, 1, 0, NOW(), NOW()),
  ('sms_template_register', '', 'sms', 'string', '注册模板ID', '注册场景的模板 id', NULL, NULL, 11, 1, 0, NOW(), NOW());

-- payment 分组 15 项（M5b spec §6）。is_public 全部为 0；非开关项都挂在本渠道开关下联动显示。
-- 1.x 未使用的 pay_wechat_api_key、pay_wechat_cert_path 不再种入。回调地址留空时按「网站地址」拼：
-- site_url + /api/payment/notify/{channel}（不用请求 Host 头拼，Host 可被伪造）。
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `is_public`, `created_at`, `updated_at`) VALUES
  ('pay_alipay_enabled', '0', 'payment', 'boolean', '启用支付宝', '是否开启支付宝支付（只影响新下单；关单、退款、回调照常处理）', NULL, NULL, 1, 1, 0, NOW(), NOW()),
  ('pay_alipay_sandbox', '0', 'payment', 'boolean', '沙箱环境', '开启后请求支付宝沙箱网关 openapi-sandbox.dl.alipaydev.com，仅用于联调', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 2, 1, 0, NOW(), NOW()),
  ('pay_alipay_app_id', '', 'payment', 'string', '支付宝AppID', '支付宝开放平台应用 AppID', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 3, 1, 0, NOW(), NOW()),
  ('pay_alipay_private_key', '', 'payment', 'string', '应用私钥', '应用私钥（RSA2），PEM 正文，可省略头尾行', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 4, 1, 0, NOW(), NOW()),
  ('pay_alipay_public_key', '', 'payment', 'string', '支付宝公钥', '支付宝公钥（公钥模式，不是应用公钥；不支持公钥证书模式）', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 5, 1, 0, NOW(), NOW()),
  ('pay_alipay_notify_url', '', 'payment', 'string', '异步通知地址', '留空则使用「网站地址」+ /api/payment/notify/alipay', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 6, 1, 0, NOW(), NOW()),
  ('pay_wechat_enabled', '0', 'payment', 'boolean', '启用微信支付', '是否开启微信支付（只影响新下单；关单、退款、回调照常处理）', NULL, NULL, 11, 1, 0, NOW(), NOW()),
  ('pay_wechat_app_id', '', 'payment', 'string', '微信AppID', '与商户号绑定的公众号、小程序或移动应用 AppID', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 12, 1, 0, NOW(), NOW()),
  ('pay_wechat_mch_id', '', 'payment', 'string', '微信商户号', '微信支付商户号', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 13, 1, 0, NOW(), NOW()),
  ('pay_wechat_api_v3_key', '', 'payment', 'string', '微信APIv3密钥', '商户平台设置的 APIv3 密钥（32 位）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 14, 1, 0, NOW(), NOW()),
  ('pay_wechat_serial_no', '', 'payment', 'string', '商户证书序列号', '商户 API 证书序列号（不是平台证书序列号）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 15, 1, 0, NOW(), NOW()),
  ('pay_wechat_private_key_path', '', 'payment', 'string', '商户私钥文件', '商户 API 私钥文件 apiclient_key.pem 的路径，相对路径从 server/ 目录算起', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 16, 1, 0, NOW(), NOW()),
  ('pay_wechat_public_key_id', '', 'payment', 'string', '微信支付公钥ID', '选填：填写后用微信支付公钥验签（PUB_KEY_ID_ 开头），须与公钥成对填写；都留空则自动下载平台证书', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 17, 1, 0, NOW(), NOW()),
  ('pay_wechat_public_key', '', 'payment', 'string', '微信支付公钥', '选填：微信支付公钥 PEM 正文，须与公钥ID成对填写', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 18, 1, 0, NOW(), NOW()),
  ('pay_wechat_notify_url', '', 'payment', 'string', '异步通知地址', '留空则使用「网站地址」+ /api/payment/notify/wechat', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 19, 1, 0, NOW(), NOW());

-- ---------------------------------------------------------------- M5a：会员管理

-- 菜单：沿用 TP8 id（9 用户管理目录，900 用户列表，901–904 按钮，910 余额记录，920 积分记录）
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (9, 0, 1, '用户管理', 'User', '/user', 'LAYOUT', '/user/user', 'i-svg:users', 'user', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 500, NOW(), NOW()),
  (900, 9, 2, '用户列表', 'UserList', '/user/user', '/user/user/index', NULL, 'i-svg:user', 'user.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (901, 900, 3, '查看详情', NULL, NULL, NULL, NULL, NULL, 'user.detail', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (902, 900, 3, '调整余额', NULL, NULL, NULL, NULL, NULL, 'user.adjust-balance', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (903, 900, 3, '调整积分', NULL, NULL, NULL, NULL, NULL, 'user.adjust-points', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (904, 900, 3, '更新状态', NULL, NULL, NULL, NULL, NULL, 'user.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (905, 900, 3, '导入用户', NULL, NULL, NULL, NULL, NULL, 'user.import', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (910, 9, 2, '余额记录', 'UserBalanceLog', '/user/balance-log', '/user/balance-log/index', NULL, 'i-svg:wallet', 'user.balance-logs', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (920, 9, 2, '积分记录', 'UserPointsLog', '/user/points-log', '/user/points-log/index', NULL, 'i-svg:star', 'user.points-logs', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (930, 9, 2, '充值订单', 'UserPaymentOrder', '/user/payment-order', '/user/payment-order/index', NULL, 'i-svg:wallet', 'payment.order.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (931, 930, 3, '查看详情', NULL, NULL, NULL, NULL, NULL, 'payment.order.detail', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (932, 930, 3, '发起退款', NULL, NULL, NULL, NULL, NULL, 'payment.order.refund', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW());

-- ---------------------------------------------------------------- M6a：微信渠道

-- 渠道配置 19 项（M6a spec §6.1）：键名、值、名称、说明、config_options、sort_order 逐字沿用 1.x
-- init.sql（wechat_official / wechat_mini）与 database/updates/v1.3.0/update.sql（wechat_open）。
-- is_public 全部为 0；secret / token / aes_key 类键名另有 SystemConfigService::isSensitiveKey() 黑名单兜底。
-- token、aes_key、encrypt_type、msg_* 在 M6a 只存不用（M6c 服务器消息端点使用）：种入是因为渠道配置页
-- 批量保存遇到未知键会整批失败。这三组不进 SystemConfigService::GROUPS（那是「系统配置」页的 tab 列表）。
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `is_public`, `created_at`, `updated_at`) VALUES
  ('wechat_official_name', '', 'wechat_official', 'string', '公众号名称', '微信公众号名称', NULL, NULL, 1, 1, 0, NOW(), NOW()),
  ('wechat_official_original_id', '', 'wechat_official', 'string', '原始ID', '公众号原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, 0, NOW(), NOW()),
  ('wechat_official_qrcode', '', 'wechat_official', 'file', '公众号二维码', '公众号二维码图片，建议 200x200', NULL, NULL, 3, 1, 0, NOW(), NOW()),
  ('wechat_official_app_id', '', 'wechat_official', 'string', 'AppID', '微信公众号AppID（开发者ID）', NULL, NULL, 10, 1, 0, NOW(), NOW()),
  ('wechat_official_app_secret', '', 'wechat_official', 'string', 'AppSecret', '微信公众号AppSecret（开发者密码）', NULL, NULL, 11, 1, 0, NOW(), NOW()),
  ('wechat_official_token', '', 'wechat_official', 'string', 'Token', '微信公众号消息校验Token', NULL, NULL, 20, 1, 0, NOW(), NOW()),
  ('wechat_official_aes_key', '', 'wechat_official', 'string', 'EncodingAESKey', '微信公众号消息加解密密钥（43位字符）', NULL, NULL, 21, 1, 0, NOW(), NOW()),
  ('wechat_official_encrypt_type', '1', 'wechat_official', 'select', '消息加密方式', '1=明文模式 2=兼容模式 3=安全模式，需与微信后台保持一致', '{"1":"明文模式","2":"兼容模式","3":"安全模式"}', NULL, 22, 1, 0, NOW(), NOW()),
  ('wechat_mini_name', '', 'wechat_mini', 'string', '小程序名称', '微信小程序名称', NULL, NULL, 1, 1, 0, NOW(), NOW()),
  ('wechat_mini_original_id', '', 'wechat_mini', 'string', '原始ID', '小程序原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, 0, NOW(), NOW()),
  ('wechat_mini_qrcode', '', 'wechat_mini', 'file', '小程序二维码', '小程序二维码图片，建议 200x200', NULL, NULL, 3, 1, 0, NOW(), NOW()),
  ('wechat_mini_app_id', '', 'wechat_mini', 'string', 'AppID', '微信小程序AppID', NULL, NULL, 10, 1, 0, NOW(), NOW()),
  ('wechat_mini_app_secret', '', 'wechat_mini', 'string', 'AppSecret', '微信小程序AppSecret', NULL, NULL, 11, 1, 0, NOW(), NOW()),
  ('wechat_mini_msg_token', '', 'wechat_mini', 'string', 'Token', '消息推送校验Token', NULL, NULL, 20, 1, 0, NOW(), NOW()),
  ('wechat_mini_msg_aes_key', '', 'wechat_mini', 'string', 'EncodingAESKey', '消息推送加解密密钥（43位字符）', NULL, NULL, 21, 1, 0, NOW(), NOW()),
  ('wechat_mini_msg_format', 'JSON', 'wechat_mini', 'select', '数据格式', '消息推送数据格式', '{"JSON":"JSON","XML":"XML"}', NULL, 22, 1, 0, NOW(), NOW()),
  ('wechat_mini_encrypt_type', '1', 'wechat_mini', 'select', '消息加密方式', '1=明文模式 2=兼容模式 3=安全模式，需与微信后台保持一致', '{"1":"明文模式","2":"兼容模式","3":"安全模式"}', NULL, 23, 1, 0, NOW(), NOW()),
  ('wechat_open_app_id', '', 'wechat_open', 'string', 'AppID', '微信开放平台网站应用AppID', NULL, NULL, 1, 1, 0, NOW(), NOW()),
  ('wechat_open_app_secret', '', 'wechat_open', 'string', 'AppSecret', '微信开放平台网站应用AppSecret', NULL, NULL, 2, 1, 0, NOW(), NOW());

-- 菜单：沿用 TP8 id（4 渠道管理目录，5 公众号/400 公众号配置，6 小程序/500 小程序配置，15 开放平台/550 开放平台配置），
-- 各列逐字沿用 1.x。配置页实际调用 /adminapi/system/config*，受 system.config.list/update 保护；这里的
-- channel.* 权限码只作菜单可见性标识。按钮 401 属于 M6c，不在 M6a 种入；410–423 见下方 M6c 块。
-- 注：i-svg:globe 在 admin/src/assets/icons 里没有对应文件（1.x 同样缺），侧栏只是不显示图标，照抄不改。
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (4, 0, 1, '渠道管理', 'Channel', '/channel', 'LAYOUT', '/channel/official/config', 'i-svg:send', 'channel', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 700, NOW(), NOW()),
  (5, 4, 1, '公众号', 'ChannelOfficial', '/channel/official', 'LAYOUT', '/channel/official/config', 'i-svg:compass', 'channel.official', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (400, 5, 2, '公众号配置', 'ChannelOfficialConfig', '/channel/official/config', '/channel/official/config', NULL, 'el-icon-Setting', 'channel.official.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (410, 5, 2, '自定义菜单', 'ChannelOfficialMenu', '/channel/official/menu', '/channel/official/menu', NULL, 'el-icon-Grid', 'channel.official.menu', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (411, 410, 3, '创建', NULL, NULL, NULL, NULL, NULL, 'channel.official.menu.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (412, 410, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'channel.official.menu.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (420, 5, 2, '自动回复', 'ChannelAutoReply', '/channel/official/auto-reply', '/channel/official/auto-reply', NULL, 'el-icon-ChatSquare', 'channel.official.auto_reply', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (421, 420, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (422, 420, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (423, 420, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (6, 4, 1, '小程序', 'ChannelMiniApp', '/channel/miniapp', 'LAYOUT', '/channel/miniapp/config', 'i-svg:smartphone', 'channel.miniapp', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (500, 6, 2, '小程序配置', 'ChannelMiniAppConfig', '/channel/miniapp/config', '/channel/miniapp/config', NULL, 'el-icon-Setting', 'channel.miniapp.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (15, 4, 1, '开放平台', 'ChannelOpen', '/channel/open', 'LAYOUT', '/channel/open/config', 'i-svg:globe', 'channel.open', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (550, 15, 2, '开放平台配置', 'ChannelOpenConfig', '/channel/open/config', '/channel/open/config', NULL, 'el-icon-Setting', 'channel.open.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

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
  (714, 710, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'announcement.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
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
  (744, 740, 3, '发布/下架', NULL, NULL, NULL, NULL, NULL, 'article.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW());

INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (8, 0, 1, '应用管理', 'Application', '/app', 'LAYOUT', '/app/region', 'i-svg:box', 'region.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 650, NOW(), NOW()),
  (800, 8, 2, '区域管理', 'AppRegion', '/app/region', '/content/region/index', NULL, 'i-svg:map-pinned', 'region.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (801, 800, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'region.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (802, 800, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'region.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (803, 800, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'region.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (810, 8, 2, '应用版本', 'AppVersion', '/app/version', '/content/version/index', NULL, 'i-svg:arrow-up-from-line', 'version.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (811, 810, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'version.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (812, 810, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'version.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (813, 810, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'version.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());


INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  -- ===== 装修（v1.8.0）=====
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

-- ---------------------------------------------------------------- M6b：消息管理菜单

-- 菜单 130–135（M6b spec §5）：避开 M4 在线管理员占用的 120/121。图标、按钮排序、redirect 照抄 1.x 的 120–125 行；
-- 130 的 sort 由 1.x 的 12 改为 13：本仓库 120 在线管理员已占 sort 12。132 标题按 spec 用「消息日志」。
-- 1.x 的 126「发送测试」按钮不种：test-send 接口不做（spec §1.2）。
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (130, 2, 1, '消息管理', 'SystemMessage', '/system/message', 'LAYOUT', '/system/message/template', 'i-svg:message-circle-more', 'system.message', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 13, NOW(), NOW()),
  (131, 130, 2, '消息模板', 'SystemMessageTemplate', '/system/message/template', '/system/message/template/index', NULL, 'el-icon-Tickets', 'system.message.template.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (132, 130, 2, '消息日志', 'SystemMessageLog', '/system/message/log', '/system/message/log/index', NULL, 'el-icon-List', 'system.message.log.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (133, 131, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.message.template.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (134, 131, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.message.template.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (135, 131, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.message.template.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());

-- 内置消息模板（M6b spec §6）：code 在 MessageTemplateRepository::BUILTIN_CODES 里登记，不可删除。
-- 只开站内信；短信、公众号、小程序全部停用且不带模板 id。字段映射只是示例，启用微信通道前须按实际选用的微信模板字段修改。
INSERT INTO `message_templates` (`name`, `code`, `remark`, `status`, `sms_enabled`, `sms_template_id`, `sms_content`, `wechat_official_enabled`, `wechat_official_template_id`, `wechat_official_url`, `wechat_official_data`, `wechat_mini_enabled`, `wechat_mini_template_id`, `wechat_mini_page`, `wechat_mini_data`, `site_enabled`, `site_title`, `site_content`, `variables`, `created_at`, `updated_at`) VALUES
  ('注册成功通知', 'user_register', NULL, 1, 0, '', '', 0, '', '', '{"thing1":"${nickname}"}', 0, '', '', '{"thing1":"${nickname}"}', 1, '注册成功', '欢迎加入，${nickname}', '[{"key":"nickname","name":"昵称","example":"张三"}]', NOW(), NOW()),
  ('充值成功通知', 'payment_success', NULL, 1, 0, '', '', 0, '', '', '{"character_string1":"${order_no}","amount2":"${amount}元","time3":"${paid_at}"}', 0, '', '', '{"character_string1":"${order_no}","amount2":"${amount}元","time3":"${paid_at}"}', 1, '充值成功', '订单 ${order_no} 已到账 ${amount} 元', '[{"key":"order_no","name":"订单号","example":"R2026091712000000000001"},{"key":"amount","name":"金额","example":"100.00"},{"key":"paid_at","name":"支付时间","example":"2026-09-17 12:00:00"}]', NOW(), NOW()),
  ('反馈已收到', 'feedback_received', NULL, 1, 0, '', '', 0, '', '', '{}', 0, '', '', '{}', 1, '反馈已收到', '您的反馈我们已收到，将尽快为您处理，感谢您的支持！', '[]', NOW(), NOW());

INSERT INTO `agreements` (`title`, `code`, `content`, `status`, `created_at`, `updated_at`) VALUES
  ('用户协议', 'user_agreement', '<p>请在后台编辑本协议。</p>', 1, NOW(), NOW()),
  ('隐私政策', 'privacy_policy', '<p>请在后台编辑本协议。</p>', 1, NOW(), NOW());

INSERT INTO `diy_pages` (`page_type`, `page_key`, `platform`, `title`, `components_draft`, `components_published`, `page_settings`, `status`, `created_at`, `updated_at`) VALUES
('home', 'home', 'uniapp', '首页', '[{"id":"seed-banner","type":"banner","props":{"items":[{"image":"","link":""}],"autoplay":true,"interval":3000,"height":300}},{"id":"seed-notice","type":"notice","props":{"items":[{"text":"欢迎使用元点Admin","link":""}],"speed":3000,"icon":""}},{"id":"seed-category-nav","type":"category-nav","props":{"style":"icon-grid","rows":2,"columns":4,"items":[{"title":"应用市场","icon":"","link":"/pages/discover/index"},{"title":"内容管理","icon":"","link":"/pages/discover/index"},{"title":"商城系统","icon":"","link":"/pages/discover/index"},{"title":"同城服务","icon":"","link":"/pages/discover/index"},{"title":"会员中心","icon":"","link":"/pages/my/index"},{"title":"支付中心","icon":"","link":"/pages/discover/index"},{"title":"数据中心","icon":"","link":"/pages/discover/index"},{"title":"全部功能","icon":"","link":"/pages/discover/index"}]}},{"id":"seed-content-list","type":"content-list","props":{"section_title":"最新文章","source":"latest","category_id":0,"limit":6,"layout":"list","show_cover":true,"show_summary":true,"show_date":true,"more_link":"/pages/discover/index"}}]', '[{"id":"seed-banner","type":"banner","props":{"items":[{"image":"","link":""}],"autoplay":true,"interval":3000,"height":300}},{"id":"seed-notice","type":"notice","props":{"items":[{"text":"欢迎使用元点Admin","link":""}],"speed":3000,"icon":""}},{"id":"seed-category-nav","type":"category-nav","props":{"style":"icon-grid","rows":2,"columns":4,"items":[{"title":"应用市场","icon":"","link":"/pages/discover/index"},{"title":"内容管理","icon":"","link":"/pages/discover/index"},{"title":"商城系统","icon":"","link":"/pages/discover/index"},{"title":"同城服务","icon":"","link":"/pages/discover/index"},{"title":"会员中心","icon":"","link":"/pages/my/index"},{"title":"支付中心","icon":"","link":"/pages/discover/index"},{"title":"数据中心","icon":"","link":"/pages/discover/index"},{"title":"全部功能","icon":"","link":"/pages/discover/index"}]}},{"id":"seed-content-list","type":"content-list","props":{"section_title":"最新文章","source":"latest","category_id":0,"limit":6,"layout":"list","show_cover":true,"show_summary":true,"show_date":true,"more_link":"/pages/discover/index"}}]', '{"background_color":""}', 1, NOW(), NOW()),
('member', 'member', 'uniapp', '个人中心', '[{"id":"seed-member-user","type":"user-info-card","props":{"show_assets":true,"assets":[{"label":"余额","stat_key":"user.balance","link":"/modules/user/pages/balance"},{"label":"积分","stat_key":"user.points","link":"/modules/user/pages/points"}]}},{"id":"seed-member-menu","type":"service-menu","props":{"items":[{"icon":"","text":"个人资料","link":"/modules/user/pages/edit-profile"},{"icon":"","text":"修改密码","link":"/modules/user/pages/change-password"},{"icon":"","text":"关于我们","link":"/modules/about/pages/about"},{"icon":"","text":"设置","link":"/modules/user/pages/settings"}]}}]', '[{"id":"seed-member-user","type":"user-info-card","props":{"show_assets":true,"assets":[{"label":"余额","stat_key":"user.balance","link":"/modules/user/pages/balance"},{"label":"积分","stat_key":"user.points","link":"/modules/user/pages/points"}]}},{"id":"seed-member-menu","type":"service-menu","props":{"items":[{"icon":"","text":"个人资料","link":"/modules/user/pages/edit-profile"},{"icon":"","text":"修改密码","link":"/modules/user/pages/change-password"},{"icon":"","text":"关于我们","link":"/modules/about/pages/about"},{"icon":"","text":"设置","link":"/modules/user/pages/settings"}]}}]', '{"background_color":""}', 1, NOW(), NOW());

INSERT INTO `mobile_configs` (`app_name`, `app_logo`, `theme_color`, `theme_colors`, `home_page`, `tabbar_json`, `tabbar_style`, `status`, `created_at`, `updated_at`) VALUES
('', '', '#2979ff', '{"primary":"#2979ff","dark":"#1e5bb8","price":"#fa3534","page_bg":"#f5f5f5","button_text":"#ffffff","badge":"#fa3534"}', '', '[{"code":"__home__","path":"pages/index/index","text":"首页","icon":"/static/tabbar/home.png","selected_icon":"/static/tabbar/home-active.png"},{"code":"__discover__","path":"pages/discover/index","text":"发现","icon":"/static/tabbar/discover.png","selected_icon":"/static/tabbar/discover-active.png"},{"code":"__message__","path":"pages/message/index","text":"消息","icon":"/static/tabbar/message.png","selected_icon":"/static/tabbar/message-active.png"},{"code":"__my__","path":"pages/my/index","text":"我的","icon":"/static/tabbar/my.png","selected_icon":"/static/tabbar/my-active.png"}]', '{"text_color":"#999999","active_color":"#2979ff","bg_color":"#ffffff"}', 1, NOW(), NOW());
