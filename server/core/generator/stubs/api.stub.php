<?php
/**
 * 前端 API 文件模板 → admin/src/api/{kebab}.ts，基于 createCrudApi()（spec 决策 13）。
 * 只使用 ModuleBlueprint::artifacts() 提供的模板变量，见 core/generator/stubs/README。
 *
 * @var string $module
 * @var string $model
 * @var string $modelKebab
 * @var list<\core\generator\ColumnDescriptor> $columns
 * @var list<\core\generator\ColumnDescriptor> $formColumns
 * @var list<\core\generator\ColumnDescriptor> $searchColumns
 * @var bool $hasStatus
 * @var \core\generator\TypeInference $inference
 */

$modelCamel = lcfirst($model);

$tsEnumType = static function (array $values): string {
    if ($values === []) {
        return 'string';
    }

    return implode(' | ', array_map(
        static fn (string $v): string => "'" . addslashes($v) . "'",
        $values
    ));
};

$tsType = static function (\core\generator\ColumnDescriptor $column, bool $forForm) use ($tsEnumType): string {
    return match ($column->type) {
        'string', 'text' => 'string',
        'integer' => 'number',
        'decimal' => $forForm ? 'number' : 'string',
        'boolean' => 'boolean',
        'date', 'datetime' => 'string',
        'enum' => $tsEnumType($column->enumValues),
        'json' => 'Record<string, any>',
        default => 'string',
    };
};

// 7.5：NOT NULL 且无默认值才是必填；表单/请求体的可选性跟这条走。
$isRequiredInForm = static function (\core\generator\ColumnDescriptor $column): bool {
    return !$column->nullable && $column->default === null;
};

$infoFields = [];
foreach ($columns as $column) {
    $infoFields[] = '    ' . $column->name . ($column->nullable ? '?' : '') . ': ' . $tsType($column, false);
}

$reqFields = [];
foreach ($formColumns as $column) {
    $reqFields[] = '    ' . $column->name . ($isRequiredInForm($column) ? '' : '?') . ': ' . $tsType($column, true);
}

$queryFields = [];
foreach ($searchColumns as $column) {
    if ($inference->queryStrategy($column) === 'range') {
        $queryFields[] = '    ' . $column->name . '_start?: string';
        $queryFields[] = '    ' . $column->name . '_end?: string';
    } else {
        $queryFields[] = '    ' . $column->name . '?: ' . $tsType($column, false);
    }
}

$basePath = "/adminapi/{$module}/{$modelKebab}";
$call = "createCrudApi<{$model}Info, {$model}Req>(\n    '{$basePath}'\n)";

$exportBlock = $hasStatus
    ? "export const {$modelCamel}Api = {$call}"
    : "export const { updateStatus: _updateStatus, ...{$modelCamel}Api } = {$call}";

$parts = [];
$parts[] = "/**\n * 由代码生成器生成，可按需修改。\n */";
$parts[] = "import type { PageQuery } from '@/types/common'\nimport { createCrudApi } from '@/utils/createCrudApi'";
$parts[] = "export interface {$model}Info {\n" . implode("\n", $infoFields) . "\n}";
$parts[] = "export interface {$model}Req {\n" . implode("\n", $reqFields) . "\n}";
$parts[] = "export interface {$model}Query extends PageQuery {\n" . implode("\n", $queryFields) . "\n}";
$parts[] = $exportBlock;

$__out = implode("\n\n", $parts);
?>
<?= $__out ?>

