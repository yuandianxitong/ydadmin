-- 由代码生成器生成，未自动执行。
-- parent_id 默认 0（顶级菜单）。要挂到已有目录下，把它改成那个目录的 id。
<?php
// SQL 字符串字面量的转义：先 \ 后 '，顺序反了等于没转义（先翻倍引号，后一步会把刚加的反斜杠又转义一遍）。
// MySQL 默认不开 NO_BACKSLASH_ESCAPES，只翻倍 ' 是不够的：以 \ 结尾的值会产出 'x\'，把后面的字段
// 一路吃进字符串里，整条 INSERT 错位——而这份 SQL 是设计上要人手工执行的。
$escape = static fn (string $v): string => str_replace(['\\', "'"], ['\\\\', "''"], $v);
// 表说明已在 ModuleBlueprint::vars() 里按 SQL 落点转义好（$tableCommentSql），这里不再转义第二次；
// 下面几个值由 module/model 派生，本身受名称白名单约束，过一道 $escape 只是纵深防御（对合法输入是恒等变换）。
$menuName = $escape(ucfirst($module) . $model);
$menuPath = $escape("/{$module}/{$modelKebab}");
$component = $escape("{$module}/{$modelKebab}/index");
$listPermission = $escape("{$module}.{$modelSnake}.list");

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
  (0, 2, '<?= $tableCommentSql ?>', '<?= $menuName ?>', '<?= $menuPath ?>', '<?= $component ?>', 'i-svg:file-text', '<?= $listPermission ?>', 1, 0, NOW(), NOW());

SET @menu_id = LAST_INSERT_ID();

INSERT INTO `menus` (`parent_id`, `type`, `title`, `permission`, `status`, `sort`, `created_at`, `updated_at`) VALUES
<?= implode(",\n", $buttonRows) ?>;
