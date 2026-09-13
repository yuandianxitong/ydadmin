<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 原始列信息 → 控件类型 / 校验规则 / cast / 查询方式（spec §7）。
 * 纯函数集合，无状态、无外部依赖。
 */
final class TypeInference
{
    /** spec §7.1：列名含这些片段一律判成图片上传控件，最先匹配。 */
    private const IMAGE_NAME_HINTS = ['image', 'avatar', 'logo', 'cover', 'pic', 'thumb'];

    /** spec §7.2：字符串类型且列名在这个白名单内才 searchable。 */
    private const SEARCHABLE_NAME_WHITELIST = [
        'name', 'title', 'username', 'nickname', 'code', 'keyword', 'label', 'email', 'phone', 'mobile',
    ];

    /**
     * spec §7.2 + 裁定：默认 in_form=true，这些列名例外。created_by/dept_id 都是系统决定
     * 的归属列，不进表单白名单——否则客户端可以自己指定所属部门，是一个提权口子。
     */
    private const IN_FORM_EXCLUDED = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'dept_id'];

    /** spec §7.2：默认 in_list=true，这个列名例外（text 家族类型的例外在 inferInList() 里按 type 判）。 */
    private const IN_LIST_EXCLUDED = ['deleted_at'];

    private const INTEGER_BASE_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];
    private const DECIMAL_BASE_TYPES = ['decimal', 'float', 'double'];
    private const TEXT_BASE_TYPES = ['tinytext', 'text', 'mediumtext', 'longtext'];
    private const STRING_BASE_TYPES = ['varchar', 'char'];
    private const DATE_BASE_TYPES = ['date'];
    private const DATETIME_BASE_TYPES = ['datetime', 'timestamp'];

    /** @param array<string, mixed> $rawColumn SHOW FULL COLUMNS 的一行 */
    public function describe(array $rawColumn): ColumnDescriptor
    {
        $name = (string) ($rawColumn['Field'] ?? '');
        $rawType = (string) ($rawColumn['Type'] ?? '');
        $nullable = (string) ($rawColumn['Null'] ?? 'NO') === 'YES';
        $default = $rawColumn['Default'] ?? null;
        $default = $default === null ? null : (string) $default;
        $comment = (string) ($rawColumn['Comment'] ?? '');
        $key = (string) ($rawColumn['Key'] ?? '');
        $extra = (string) ($rawColumn['Extra'] ?? '');

        $baseType = $this->baseType($rawType);
        $type = $this->normalizeType($baseType, $rawType);
        $enumValues = $type === 'enum' ? $this->parseEnumValues($rawType) : [];

        return new ColumnDescriptor(
            $name,
            $type,
            $rawType,
            $nullable,
            $default,
            $comment,
            $key,
            $extra,
            $this->inferFormType($name, $rawType, $baseType),
            $this->inferSearchable($name, $type),
            $this->inferInList($name, $type),
            $this->inferInForm($name),
            $enumValues,
        );
    }

    /**
     * spec §7.5（勘误版）+ 裁定。unique 列**不生成唯一性规则**（唯一性改为 Service 层
     * `existsBy{Column}()` 查重 + `UniqueConstraintViolationException` 兜底），但类型与长度
     * 规则照常产出——`key === 'UNI'` 不再是短路分支，只影响 Service/Controller 模板要不要
     * 拼一条唯一性校验（它们根本不拼）。原措辞「unique 索引不生成校验规则」曾被字面实现成
     * 连类型规则也不生成，导致 `varchar(100) UNIQUE` 的列（如 slug）能塞进任意长度的值，
     * 由数据库在严格模式报错、非严格模式静默截断——已改正。
     * status/sort 固定覆盖式规则；NOT NULL 但有默认值的列产出 `sometimes|required`
     * （客户端可以不传，传了才校验）；NOT NULL 且无默认值的列不产出必填部分，交由模板按
     * 创建/更新场景用 {$required} 拼。
     */
    public function validationRule(ColumnDescriptor $column): string
    {
        $lowerName = strtolower($column->name);
        if ($lowerName === 'status') {
            return 'sometimes|required|integer|in:0,1';
        }
        if ($lowerName === 'sort') {
            return 'sometimes|required|integer|min:0';
        }

        $parts = [];
        if ($column->nullable) {
            $parts[] = 'nullable';
        } elseif ($column->default !== null) {
            $parts[] = 'sometimes|required';
        }

        switch ($column->type) {
            case 'string':
                $parts[] = 'string|max:' . $this->varcharLength($column->rawType);
                break;
            case 'text':
                $parts[] = 'string';
                break;
            case 'integer':
                $parts[] = 'integer';
                break;
            case 'decimal':
                $parts[] = 'numeric';
                break;
            case 'enum':
                $parts[] = 'in:' . implode(',', $column->enumValues);
                break;
            case 'date':
            case 'datetime':
                $parts[] = 'date';
                break;
        }

        if (str_contains($lowerName, 'email')) {
            $parts[] = 'email';
        }

        return implode('|', $parts);
    }

    /**
     * 追加裁定：这一列实际会得到哪些校验规则，按顺序返回消息键的后缀 token（lang.stub 与
     * controller.stub 都迭代它来产出/引用校验消息键：`{model_snake}_{column}_{token}`，
     * 两边因此不可能在键名上分叉）。
     *
     * 结构上刻意跟 validationRule() 共享同一套判断分支（是否可空、是否有默认值、类型
     * switch、status/sort 的固定覆盖），只是产出格式不同：一个是 Laravel 规则字符串，
     * 一个是消息键的 token 列表。NOT NULL 的列不管有没有默认值都产出 require token：
     * 有默认值时 validationRule() 直接写 sometimes|required，没有默认值时 create 场景下
     * 模板会拼上裸 required——两种情况运行时都会真的应用一条 required 规则，都需要
     * 对应的消息键。二者对同一列必须严格对应（有 max: 就必须有 length，有 in: 就必须有
     * invalid，反之亦然）——由 TypeInferenceTest 的交叉校验测试钉死。
     *
     * unique 列同样不再短路：唯一性不体现在这里（那是 Service 层的事），类型/长度对应的
     * token 照常产出，与 validationRule() 的勘误保持一致。
     *
     * @return list<string>
     */
    public function ruleTokens(ColumnDescriptor $column): array
    {
        $lowerName = strtolower($column->name);
        if ($lowerName === 'status') {
            return ['require', 'integer', 'invalid'];
        }
        if ($lowerName === 'sort') {
            return ['require', 'integer', 'min'];
        }

        $tokens = [];
        if (!$column->nullable) {
            $tokens[] = 'require';
        }

        switch ($column->type) {
            case 'string':
                $tokens[] = 'length';
                break;
            case 'integer':
                $tokens[] = 'integer';
                break;
            case 'decimal':
                $tokens[] = 'numeric';
                break;
            case 'enum':
                $tokens[] = 'invalid';
                break;
            case 'date':
            case 'datetime':
                $tokens[] = 'date';
                break;
        }

        if (str_contains($lowerName, 'email')) {
            $tokens[] = 'email';
        }

        return $tokens;
    }

    /** spec §7.6。 */
    public function cast(ColumnDescriptor $column): ?string
    {
        return match ($column->type) {
            'integer' => 'int',
            'boolean' => 'boolean',
            'decimal' => 'string',
            'json' => 'array',
            'date', 'datetime' => 'datetime',
            default => null,
        };
    }

    /**
     * spec §7.4：字符串 → like，整型/enum → equal（含 status，因为它的归一化类型就是 integer），
     * 日期时间 → range。这是 ColumnDescriptor 的纯函数，不看 searchable——是否真的把某列拼进
     * 生成的搜索条件，是 ModuleBlueprint 按 $searchColumns（searchable === true）另外决定的事。
     */
    public function queryStrategy(ColumnDescriptor $column): string
    {
        if (in_array($column->type, ['date', 'datetime'], true)) {
            return 'range';
        }
        if (in_array($column->type, ['string', 'text'], true)) {
            return 'like';
        }

        return 'equal';
    }

    private function baseType(string $rawType): string
    {
        if (preg_match('/^([a-z]+)/i', trim($rawType), $m) === 1) {
            return strtolower($m[1]);
        }

        return strtolower(trim($rawType));
    }

    private function normalizeType(string $baseType, string $rawType): string
    {
        if ($baseType === 'enum') {
            return 'enum';
        }
        if ($baseType === 'json') {
            return 'json';
        }
        if (in_array($baseType, self::STRING_BASE_TYPES, true)) {
            return 'string';
        }
        if (in_array($baseType, self::TEXT_BASE_TYPES, true)) {
            return 'text';
        }
        if (in_array($baseType, self::DECIMAL_BASE_TYPES, true)) {
            return 'decimal';
        }
        if (in_array($baseType, self::DATE_BASE_TYPES, true)) {
            return 'date';
        }
        if (in_array($baseType, self::DATETIME_BASE_TYPES, true)) {
            return 'datetime';
        }
        if ($baseType === 'tinyint' && $this->isTinyintOne($rawType)) {
            return 'boolean';
        }
        if (in_array($baseType, self::INTEGER_BASE_TYPES, true)) {
            return 'integer';
        }

        return 'string';
    }

    private function isTinyintOne(string $rawType): bool
    {
        return preg_match('/^tinyint\(1\)/i', trim($rawType)) === 1;
    }

    /** @return list<string> */
    private function parseEnumValues(string $rawType): array
    {
        if (preg_match('/^enum\((.*)\)$/i', trim($rawType), $m) !== 1) {
            return [];
        }

        preg_match_all("/'([^']*)'/", $m[1], $values);

        return $values[1];
    }

    private function inferFormType(string $name, string $rawType, string $baseType): string
    {
        $lowerName = strtolower($name);

        foreach (self::IMAGE_NAME_HINTS as $hint) {
            if (str_contains($lowerName, $hint)) {
                return 'image';
            }
        }
        if ($baseType === 'enum') {
            return 'select';
        }
        if ($lowerName === 'status' || str_starts_with($lowerName, 'is_') || $this->isTinyintOne($rawType)) {
            return 'switch';
        }
        if (in_array($baseType, self::DATE_BASE_TYPES, true) || in_array($baseType, self::DATETIME_BASE_TYPES, true)) {
            return 'datepicker';
        }
        if (in_array($baseType, self::TEXT_BASE_TYPES, true)) {
            return 'textarea';
        }
        if (in_array($baseType, self::INTEGER_BASE_TYPES, true) || in_array($baseType, self::DECIMAL_BASE_TYPES, true)) {
            return 'number';
        }

        return 'input';
    }

    private function inferSearchable(string $name, string $type): bool
    {
        $lowerName = strtolower($name);
        if ($lowerName === 'status') {
            return true;
        }

        return $type === 'string' && in_array($lowerName, self::SEARCHABLE_NAME_WHITELIST, true);
    }

    private function inferInList(string $name, string $type): bool
    {
        if (in_array(strtolower($name), self::IN_LIST_EXCLUDED, true)) {
            return false;
        }

        return $type !== 'text';
    }

    private function inferInForm(string $name): bool
    {
        return !in_array(strtolower($name), self::IN_FORM_EXCLUDED, true);
    }

    private function varcharLength(string $rawType): int
    {
        return preg_match('/\((\d+)\)/', $rawType, $m) === 1 ? (int) $m[1] : 255;
    }
}
