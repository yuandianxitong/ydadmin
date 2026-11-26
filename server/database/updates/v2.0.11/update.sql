UPDATE `menus`
SET `parent_id` = 2, `path` = '/system/region', `sort` = 14, `updated_at` = NOW()
WHERE `id` = 800;

UPDATE `menus`
SET `parent_id` = 2, `path` = '/system/version', `sort` = 15, `updated_at` = NOW()
WHERE `id` = 810;

UPDATE `menus`
SET `type` = 2, `component` = '/app/index', `redirect` = NULL, `permission` = 'plugin.list', `updated_at` = NOW()
WHERE `id` = 8;

INSERT IGNORE INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (821, 8, 3, '安装', NULL, NULL, NULL, NULL, NULL, 'plugin.install', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (822, 8, 3, '启用', NULL, NULL, NULL, NULL, NULL, 'plugin.enable', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (823, 8, 3, '停用', NULL, NULL, NULL, NULL, NULL, 'plugin.disable', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (824, 8, 3, '升级', NULL, NULL, NULL, NULL, NULL, 'plugin.upgrade', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (825, 8, 3, '卸载', NULL, NULL, NULL, NULL, NULL, 'plugin.uninstall', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (826, 8, 3, '清除数据', NULL, NULL, NULL, NULL, NULL, 'plugin.purge', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 6, NOW(), NOW());
