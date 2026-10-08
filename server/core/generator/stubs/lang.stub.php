<?= '<?php' ?>


<?php
// 键名统一由 TypeInference::ruleTokens() 决定，本模板不再自己推导校验规则字符串——
// controller.stub.php 的 messages() 迭代同一个方法产出键名，两边因此不会分叉（第二轮裁定）。
$zhLabel = static fn (\core\generator\ColumnDescriptor $c): string => $c->comment !== '' ? $c->comment : $c->name;
$enLabel = static fn (\core\generator\ColumnDescriptor $c): string => ucwords(str_replace('_', ' ', $c->name));
$labelOf = static fn (\core\generator\ColumnDescriptor $c): string => $locale === 'en' ? $enLabel($c) : $zhLabel($c);

$maxLength = static function (\core\generator\ColumnDescriptor $c): string {
    return preg_match('/\((\d+)/', $c->rawType, $m) ? $m[1] : '';
};

// 一个模型一个文件：resource/lang/{locale}/{module}/{modelSnake}.php。
// 同模块的下一个模型写到另一个文件。生成器只创建新文件，写进同一个文件会被跳过，第二个模型的键就丢了。
$key = static fn (string $suffix): string => $suffix;

$phrases = $locale === 'en' ? [
    'require' => '%s is required',
    'length'  => '%s must not exceed %s characters',
    'integer' => '%s must be an integer',
    'numeric' => '%s must be numeric',
    'min'     => '%s must not be less than %s',
    'invalid' => '%s is invalid',
    'date'    => '%s format is invalid',
    'email'   => '%s format is invalid',
    'boolean' => '%s must be 0 or 1',
    'array'   => '%s must be an array',
    'exists'  => '%s already exists',
] : [
    'require' => '%s不能为空',
    'length'  => '%s长度不能超过%s个字符',
    'integer' => '%s必须是整数',
    'numeric' => '%s必须是数字',
    'min'     => '%s不能小于%s',
    'invalid' => '%s的值无效',
    'date'    => '%s格式不正确',
    'email'   => '%s格式不正确',
    'boolean' => '%s只能是 0 或 1',
    'array'   => '%s必须是数组',
    'exists'  => '%s已存在',
];
$phrase = static function (string $kind, string $label, string $extra = '') use ($phrases): string {
    return sprintf($phrases[$kind], $label, $extra);
};

// ruleTokens() 只给 token 名（'length'/'min'/...），不给规则里的数字。目前 spec §7.5 唯一会产出
// length 的是 varchar/char 的 max:n（从 rawType 的括号里取），唯一会产出 min 的是 sort 列的
// min:0（写死 '0'）。以后规则表新增别的数值型 token，要把这处改成真的从规则里解析数字。
$extraFor = static fn (string $token, \core\generator\ColumnDescriptor $c): string => match ($token) {
    'length' => $maxLength($c),
    'min' => '0',
    default => '',
};

$lines = [];
// 表说明用剥过换行的那个变量：这一行最终会被 addslashes() 塞进单引号字面量，换行虽然合法但会
// 把语言包排版打散；$tableComment 的原值一律不直接进产物（见 ModuleBlueprint::vars()）。
$lines[$key('not_found')] = $locale === 'en' ? "{$model} not found" : "{$tableCommentPhpDoc}不存在";

foreach ($uniqueColumns as $uniqueColumn) {
    $col = $table->column($uniqueColumn);
    if ($col === null) {
        continue;
    }
    $lines[$key("{$uniqueColumn}_exists")] = $phrase('exists', $labelOf($col));
}

foreach ($formColumns as $col) {
    $label = $labelOf($col);
    foreach ($inference->ruleTokens($col) as $token) {
        $lines[$key("{$col->name}_{$token}")] = $phrase($token, $label, $extraFor($token, $col));
    }
}

// 批量删除是每个生成模块固定有的端点，ids 校验消息不挂在任何字段上。
$lines[$key('ids_require')] = $locale === 'en' ? 'Please select records to delete' : '请选择要删除的数据';
$lines[$key('ids_integer')] = $locale === 'en' ? 'ID must be an integer' : 'ID必须是整数';

$out = "// 由代码生成器生成（{$locale}）。一个模型一个文件，键不再加模型前缀。\n"
    . "// 调用方用 lang('{$module}/{$modelSnake}.键名')，同模块的下一个模型落到另一个文件。\n"
    . "return [\n";
foreach ($lines as $k => $text) {
    $out .= "    '{$k}' => '" . addslashes($text) . "',\n";
}
$out .= "];\n";

echo $out;
