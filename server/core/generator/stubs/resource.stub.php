<?php
// 输出字段与 OpenAPI 使用同一份声明；扩展业务字段时在生成的 Resource 中补充。
$properties = [];
foreach ($columns as $column) {
    $schema = match ($column->type) {
        'integer' => ['type' => 'integer'],
        'boolean' => ['type' => 'boolean'],
        'json' => [],
        default => ['type' => 'string'],
    };
    if ($column->type === 'enum') {
        $schema['enum'] = $column->enumValues;
    }
    if ($column->nullable) {
        $schema['nullable'] = true;
    }
    $properties[$column->name] = $schema;
}
if ($hasStatus) {
    $properties['status_text'] = ['type' => 'string'];
}
$export = static function (mixed $value) use (&$export): string {
    if (!is_array($value)) {
        return var_export($value, true);
    }
    $parts = [];
    foreach ($value as $key => $item) {
        $parts[] = (array_is_list($value) ? '' : var_export($key, true) . ' => ') . $export($item);
    }

    return '[' . implode(', ', $parts) . ']';
};
?>
<?= '<?php' ?>


declare(strict_types=1);

namespace app\resource\<?= $module ?>;

use core\response\JsonResource;

final class <?= $model ?>Resource extends JsonResource
{
    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['<?= $primaryKey ?>'],
            'additionalProperties' => false,
            'properties' => [
<?php foreach ($properties as $name => $schema): ?>
                '<?= $name ?>' => <?= $export($schema) ?>,
<?php endforeach; ?>
            ],
        ];
    }
}
