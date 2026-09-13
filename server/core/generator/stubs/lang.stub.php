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

// 生成模块一个语言文件对应一个模块（而不是一个模型）：键统一加 {modelSnake}_ 前缀，
// 同一模块以后再生成别的模型时，不含字段名的键（not_found/{col}_exists/ids_require）与
// 字段级键（{col}_{token}）都不会互相覆盖。
$key = static fn (string $suffix): string => "{$modelSnake}_{$suffix}";

$phrases = $locale === 'en' ? [
    'require' => '%s is required',
    'length'  => '%s must not exceed %s characters',
    'integer' => '%s must be an integer',
    'numeric' => '%s must be numeric',
    'min'     => '%s must not be less than %s',
    'invalid' => '%s is invalid',
    'date'    => '%s format is invalid',
    'email'   => '%s format is invalid',
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
$lines[$key('not_found')] = $locale === 'en' ? "{$model} not found" : "{$tableComment}不存在";

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

// 批量删除是每个生成模块固定有的端点，ids 校验消息不挂在任何字段上，同样加模型前缀。
$lines[$key('ids_require')] = $locale === 'en' ? 'Please select records to delete' : '请选择要删除的数据';
$lines[$key('ids_integer')] = $locale === 'en' ? 'ID must be an integer' : 'ID必须是整数';

$out = "// 由代码生成器生成（{$locale}）。business.php / validation.php 是手写模块共用文件，\n"
    . "// 生成模块用独立分组 {$module}.*，键统一加 {$modelSnake}_ 前缀，同模块以后生成别的模型不会互相覆盖。\n"
    . "return [\n";
foreach ($lines as $k => $text) {
    $out .= "    '{$k}' => '" . addslashes($text) . "',\n";
}
$out .= "];\n";

echo $out;
