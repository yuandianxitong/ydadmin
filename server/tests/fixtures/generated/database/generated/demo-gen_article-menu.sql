-- 由代码生成器生成，未自动执行。
-- parent_id 默认 0（顶级菜单）。要挂到已有目录下，把它改成那个目录的 id。
INSERT INTO `menus`
  (`parent_id`, `type`, `title`, `name`, `path`, `component`, `icon`, `permission`, `status`, `sort`, `created_at`, `updated_at`)
VALUES
  (0, 2, '生成器夹具表', 'DemoGenArticle', '/demo/gen-article', 'demo/gen-article/index', 'i-svg:file-text', 'demo.gen_article.list', 1, 0, NOW(), NOW());

SET @menu_id = LAST_INSERT_ID();

INSERT INTO `menus` (`parent_id`, `type`, `title`, `permission`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (@menu_id, 3, '新增', 'demo.gen_article.create', 1, 1, NOW(), NOW()),
  (@menu_id, 3, '编辑', 'demo.gen_article.update', 1, 2, NOW(), NOW()),
  (@menu_id, 3, '删除', 'demo.gen_article.delete', 1, 3, NOW(), NOW()),
  (@menu_id, 3, '状态', 'demo.gen_article.status', 1, 4, NOW(), NOW());
