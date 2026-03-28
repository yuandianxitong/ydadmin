-- ============================================================
-- v1.2.1 升级脚本
-- 修复：菜单权限命名不一致、缺失按钮权限
-- 适用于从 v1.2.0 及更早版本升级
-- ============================================================

-- =============================================
-- 1. 修正系统模块 type=2 菜单权限命名（添加 .list 后缀）
-- =============================================
UPDATE `menus` SET `permission` = 'system.admin.list' WHERE `id` = 10;
UPDATE `menus` SET `permission` = 'system.role.list' WHERE `id` = 20;
UPDATE `menus` SET `permission` = 'system.department.list' WHERE `id` = 30;
UPDATE `menus` SET `permission` = 'system.permission.list' WHERE `id` = 40;
UPDATE `menus` SET `permission` = 'system.menu.list' WHERE `id` = 50;
UPDATE `menus` SET `permission` = 'system.dictionary.list' WHERE `id` = 60;
UPDATE `menus` SET `permission` = 'system.file.list' WHERE `id` = 70;
UPDATE `menus` SET `permission` = 'system.notification.list' WHERE `id` = 80;
UPDATE `menus` SET `permission` = 'system.cron_job.list' WHERE `id` = 90;
UPDATE `menus` SET `permission` = 'system.config.list' WHERE `id` = 100;
UPDATE `menus` SET `permission` = 'system.message.template.list' WHERE `id` = 121;
UPDATE `menus` SET `permission` = 'system.message.log.list' WHERE `id` = 122;
UPDATE `menus` SET `permission` = 'system.generator.list' WHERE `id` = 200;

-- =============================================
-- 2. 新增缺失的 type=3 按钮菜单
-- =============================================
INSERT INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  -- 管理员管理 - 状态
  (14, 10, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'system.admin.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  -- 角色管理 - 状态
  (25, 20, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'system.role.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  -- 文件管理 - 编辑
  (72, 70, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.file.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  -- 定时任务 - 清空日志
  (95, 90, 3, '清空日志', NULL, NULL, NULL, NULL, NULL, 'system.cron_job.clear', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  -- 系统配置 - 编辑
  (101, 100, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.config.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  -- 日志管理 - 删除/清空
  (113, 110, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.log.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (114, 110, 3, '清空', NULL, NULL, NULL, NULL, NULL, 'system.log.clear', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  -- 消息模板 - 增删改 + 发送测试
  (123, 121, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.message.template.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (124, 121, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.message.template.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (125, 121, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.message.template.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (126, 121, 3, '发送测试', NULL, NULL, NULL, NULL, NULL, 'system.message.template.send', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  -- 代码生成器 - 生成
  (201, 200, 3, '生成', NULL, NULL, NULL, NULL, NULL, 'system.generator.generate', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  -- 公告管理 - 状态
  (714, 710, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'announcement.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  -- 公众号配置 - 发送模板
  (401, 400, 3, '发送模板', NULL, NULL, NULL, NULL, NULL, 'channel.official.config.send', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  -- 自定义菜单 - 创建/删除
  (411, 410, 3, '创建', NULL, NULL, NULL, NULL, NULL, 'channel.official.menu.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (412, 410, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'channel.official.menu.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  -- 自动回复 - 增删改
  (421, 420, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (422, 420, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (423, 420, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());

-- =============================================
-- 3. 为超级管理员角色分配新增按钮
-- =============================================
INSERT INTO `role_menus` (`role_id`, `menu_id`, `created_at`, `updated_at`) VALUES
  (1, 14, NOW(), NOW()),
  (1, 25, NOW(), NOW()),
  (1, 72, NOW(), NOW()),
  (1, 95, NOW(), NOW()),
  (1, 101, NOW(), NOW()),
  (1, 113, NOW(), NOW()),
  (1, 114, NOW(), NOW()),
  (1, 123, NOW(), NOW()),
  (1, 124, NOW(), NOW()),
  (1, 125, NOW(), NOW()),
  (1, 126, NOW(), NOW()),
  (1, 201, NOW(), NOW()),
  (1, 714, NOW(), NOW()),
  (1, 401, NOW(), NOW()),
  (1, 411, NOW(), NOW()),
  (1, 412, NOW(), NOW()),
  (1, 421, NOW(), NOW()),
  (1, 422, NOW(), NOW()),
  (1, 423, NOW(), NOW());

-- =============================================
-- 4. 执行完毕后需清除应用缓存
--    php think clear
-- =============================================
