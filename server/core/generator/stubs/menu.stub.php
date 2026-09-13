-- 由代码生成器生成，未自动执行。
-- parent_id 默认 0（顶级菜单）。要挂到已有目录下，把它改成那个目录的 id。
<?php
$escape = static fn (string $v): string => str_replace("'", "''", $v);
$menuName = ucfirst($module) . $model;
$menuPath = "/{$module}/{$modelKebab}";
$component = "{$module}/{$modelKebab}/index";
$listPermission = "{$module}.{$modelSnake}.list";

$buttonRows = [
    "  (@menu_id, 3, '新增', '{$module}.{$modelSnake}.create', 1, 1, NOW(), NOW())",
    "  (@menu_id, 3, '编辑', '{$module}.{$modelSnake}.update', 1, 2, NOW(), NOW())",
    "  (@menu_id, 3, '删除', '{$module}.{$modelSnake}.delete', 1, 3, NOW(), NOW())",
];
if ($hasStatus) {
    $buttonRows[] = "  (@menu_id, 3, '状态', '{$module}.{$modelSnake}.status', 1, 4, NOW(), NOW())";
}
?>
INSERT INTO `menus`
  (`parent_id`, `type`, `title`, `name`, `path`, `component`, `icon`, `permission`, `status`, `sort`, `created_at`, `updated_at`)
VALUES
  (0, 2, '<?= $escape($tableComment) ?>', '<?= $menuName ?>', '<?= $menuPath ?>', '<?= $component ?>', 'i-svg:file-text', '<?= $listPermission ?>', 1, 0, NOW(), NOW());

SET @menu_id = LAST_INSERT_ID();

INSERT INTO `menus` (`parent_id`, `type`, `title`, `permission`, `status`, `sort`, `created_at`, `updated_at`) VALUES
<?= implode(",\n", $buttonRows) ?>;
