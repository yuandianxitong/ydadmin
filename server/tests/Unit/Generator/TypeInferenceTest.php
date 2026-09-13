<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\ColumnDescriptor;
use core\generator\TypeInference;
use tests\TestCase;

final class TypeInferenceTest extends TestCase
{
    private TypeInference $inference;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inference = new TypeInference();
    }

    /** @return list<array<string, mixed>> */
    private function goldenRawColumns(): array
    {
        return [
            ['Field' => 'id', 'Type' => 'int unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment', 'Comment' => ''],
            ['Field' => 'title', 'Type' => 'varchar(200)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '标题'],
            ['Field' => 'summary', 'Type' => 'varchar(500)', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '摘要'],
            ['Field' => 'content', 'Type' => 'longtext', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '正文'],
            ['Field' => 'cover_image', 'Type' => 'varchar(255)', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '封面图'],
            ['Field' => 'category', 'Type' => "enum('news','tech','life')", 'Null' => 'NO', 'Key' => '', 'Default' => 'news', 'Extra' => '', 'Comment' => '分类'],
            ['Field' => 'price', 'Type' => 'decimal(10,2)', 'Null' => 'NO', 'Key' => '', 'Default' => '0.00', 'Extra' => '', 'Comment' => '价格'],
            ['Field' => 'view_count', 'Type' => 'int unsigned', 'Null' => 'NO', 'Key' => '', 'Default' => '0', 'Extra' => '', 'Comment' => '浏览量'],
            ['Field' => 'slug', 'Type' => 'varchar(100)', 'Null' => 'NO', 'Key' => 'UNI', 'Default' => null, 'Extra' => '', 'Comment' => '别名'],
            ['Field' => 'published_at', 'Type' => 'datetime', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '发布时间'],
            ['Field' => 'status', 'Type' => 'tinyint', 'Null' => 'NO', 'Key' => 'MUL', 'Default' => '1', 'Extra' => '', 'Comment' => '状态'],
            ['Field' => 'sort', 'Type' => 'int', 'Null' => 'NO', 'Key' => '', 'Default' => '0', 'Extra' => '', 'Comment' => '排序'],
            ['Field' => 'created_by', 'Type' => 'int unsigned', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => ''],
            ['Field' => 'dept_id', 'Type' => 'int unsigned', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => ''],
            ['Field' => 'created_at', 'Type' => 'datetime', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => ''],
            ['Field' => 'updated_at', 'Type' => 'datetime', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => ''],
            ['Field' => 'deleted_at', 'Type' => 'datetime', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => ''],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: bool, 4: bool, 5: bool, 6: ?string}> */
    private function expectations(): array
    {
        return [
            ['id', 'integer', 'number', false, true, false, 'int'],
            ['title', 'string', 'input', true, true, true, null],
            ['summary', 'string', 'input', false, true, true, null],
            ['content', 'text', 'textarea', false, false, true, null],
            ['cover_image', 'string', 'image', false, true, true, null],
            ['category', 'enum', 'select', false, true, true, null],
            ['price', 'decimal', 'number', false, true, true, 'string'],
            ['view_count', 'integer', 'number', false, true, true, 'int'],
            ['slug', 'string', 'input', false, true, true, null],
            ['published_at', 'datetime', 'datepicker', false, true, true, 'datetime'],
            ['status', 'integer', 'switch', true, true, true, 'int'],
            ['sort', 'integer', 'number', false, true, true, 'int'],
            ['created_by', 'integer', 'number', false, true, false, 'int'],
            ['dept_id', 'integer', 'number', false, true, false, 'int'],
            ['created_at', 'datetime', 'datepicker', false, true, false, 'datetime'],
            ['updated_at', 'datetime', 'datepicker', false, true, false, 'datetime'],
            ['deleted_at', 'datetime', 'datepicker', false, false, false, 'datetime'],
        ];
    }

    /** @return array<string, ColumnDescriptor> */
    private function describedColumns(): array
    {
        $columns = [];
        foreach ($this->goldenRawColumns() as $raw) {
            $column = $this->inference->describe($raw);
            $columns[$column->name] = $column;
        }

        return $columns;
    }

    public function test_golden_fixture_columns_are_described_correctly(): void
    {
        $columns = $this->describedColumns();

        foreach ($this->expectations() as [$name, $type, $formType, $searchable, $inList, $inForm, $cast]) {
            $column = $columns[$name];

            $this->assertSame($type, $column->type, "{$name}.type");
            $this->assertSame($formType, $column->formType, "{$name}.form_type");
            $this->assertSame($searchable, $column->searchable, "{$name}.searchable");
            $this->assertSame($inList, $column->inList, "{$name}.in_list");
            $this->assertSame($inForm, $column->inForm, "{$name}.in_form");
            $this->assertSame($cast, $this->inference->cast($column), "{$name}.cast");
        }
    }

    public function test_category_enum_values_are_parsed_from_the_raw_type(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame(['news', 'tech', 'life'], $columns['category']->enumValues);
        $this->assertSame([], $columns['title']->enumValues);
    }

    public function test_nullable_reflects_the_raw_null_column(): void
    {
        $columns = $this->describedColumns();

        $this->assertFalse($columns['title']->nullable);
        $this->assertTrue($columns['summary']->nullable);
    }

    /**
     * 底稿 §5 表格「查询」列只对 4 列给出明确值：title→like、category→equal、
     * published_at→range、status→equal；其余列在表里是「—」。查询方式是
     * ColumnDescriptor 的纯函数（签名只能返回 like/equal/range 三者之一，没有「不适用」
     * 这个第四态），所以「—」只能理解成「这些列不会被拼进生成的搜索条件里」（那是
     * ModuleBlueprint 按 searchable 选 $searchColumns 的职责，不是 TypeInference 的
     * 职责），而不是「queryStrategy() 对它们返回别的值」。本方法按 spec §7.4 的字面
     * 类型规则实现（字符串→like，整型/enum→equal，日期时间→range），下面先钉死
     * 表格明确给出的 4 列，再用剩余类型分别断言纯函数行为。
     */
    public function test_query_strategy_matches_the_four_values_the_golden_table_specifies(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('like', $this->inference->queryStrategy($columns['title']));
        $this->assertSame('equal', $this->inference->queryStrategy($columns['category']));
        $this->assertSame('range', $this->inference->queryStrategy($columns['published_at']));
        $this->assertSame('equal', $this->inference->queryStrategy($columns['status']));
    }

    public function test_query_strategy_is_a_pure_function_of_type_for_the_remaining_columns(): void
    {
        $columns = $this->describedColumns();

        // 字符串类型（无论 searchable）→ like
        $this->assertSame('like', $this->inference->queryStrategy($columns['summary']));
        $this->assertSame('like', $this->inference->queryStrategy($columns['slug']));

        // 整型（无论 searchable）→ equal
        $this->assertSame('equal', $this->inference->queryStrategy($columns['id']));
        $this->assertSame('equal', $this->inference->queryStrategy($columns['view_count']));
        $this->assertSame('equal', $this->inference->queryStrategy($columns['sort']));

        // 日期时间（无论 searchable）→ range
        $this->assertSame('range', $this->inference->queryStrategy($columns['created_at']));
        $this->assertSame('range', $this->inference->queryStrategy($columns['deleted_at']));
    }

    /**
     * spec §7.5（勘误版）：unique 索引只是不生成*唯一性*校验规则（改为 Service 层
     * existsBy{Column}() 查重 + UniqueConstraintViolationException 兜底），类型与长度
     * 规则照常产出——否则 varchar(100) UNIQUE 的列能塞进任意长度的值，靠数据库严格模式
     * 报错或非严格模式静默截断。
     */
    public function test_unique_column_still_gets_type_and_length_rule(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('string|max:100', $this->inference->validationRule($columns['slug']));
    }

    /** spec §7.5：status/sort 是固定覆盖式规则，不看是否可空或是否有默认值。 */
    public function test_status_and_sort_have_fixed_override_rules(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('sometimes|required|integer|in:0,1', $this->inference->validationRule($columns['status']));
        $this->assertSame('sometimes|required|integer|min:0', $this->inference->validationRule($columns['sort']));
    }

    /** title 是 NOT NULL 且无默认值：必填部分交由模板用 {$required} 拼，这里不产出。 */
    public function test_validation_rule_for_not_null_varchar_column_without_default(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('string|max:200', $this->inference->validationRule($columns['title']));
    }

    public function test_validation_rule_for_nullable_varchar_column(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('nullable|string|max:500', $this->inference->validationRule($columns['summary']));
        $this->assertSame('nullable|string|max:255', $this->inference->validationRule($columns['cover_image']));
    }

    /** text/longtext 没有长度上限，补 string 但不补 max:。 */
    public function test_validation_rule_for_nullable_text_column_has_no_length_rule(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('nullable|string', $this->inference->validationRule($columns['content']));
    }

    /** category 是 NOT NULL 但有默认值 'news'：客户端可以不传，传了才校验。 */
    public function test_validation_rule_for_enum_column_not_null_with_default(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('sometimes|required|in:news,tech,life', $this->inference->validationRule($columns['category']));
    }

    /** price 是 NOT NULL 但有默认值 '0.00'。 */
    public function test_validation_rule_for_decimal_column_not_null_with_default(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('sometimes|required|numeric', $this->inference->validationRule($columns['price']));
    }

    /** view_count 是 NOT NULL 但有默认值 '0'。 */
    public function test_validation_rule_for_not_null_integer_column_with_default(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('sometimes|required|integer', $this->inference->validationRule($columns['view_count']));
    }

    /** id 同样 NOT NULL，但没有字面默认值（AUTO_INCREMENT）：不产出 sometimes|required 前缀。 */
    public function test_validation_rule_for_not_null_integer_column_without_default(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('integer', $this->inference->validationRule($columns['id']));
    }

    public function test_validation_rule_for_nullable_integer_column(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('nullable|integer', $this->inference->validationRule($columns['created_by']));
        $this->assertSame('nullable|integer', $this->inference->validationRule($columns['dept_id']));
    }

    /** date/datetime 的类型片段是 'date'，可空与必填仍由既有的 nullable/required 分支决定。 */
    public function test_validation_rule_for_nullable_datetime_column_includes_the_date_rule(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame('nullable|date', $this->inference->validationRule($columns['published_at']));
        $this->assertSame('nullable|date', $this->inference->validationRule($columns['created_at']));
    }

    /** spec §7.5：列名含 email 追加 |email（夹具表没有这一列，单独构造）。 */
    public function test_validation_rule_appends_email_when_name_contains_email(): void
    {
        $column = $this->inference->describe([
            'Field' => 'contact_email', 'Type' => 'varchar(100)', 'Null' => 'YES',
            'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '联系邮箱',
        ]);

        $this->assertSame('nullable|string|max:100|email', $this->inference->validationRule($column));
    }

    /**
     * tinyint(1) 是唯一映射到 boolean 的原始类型；夹具表里没有这一列，单独构造覆盖分支。
     *
     * validationRule()/ruleTokens() 两条断言是补上的：这条用例原先只断言 type/formType/cast，
     * 而 validationRule() 的 switch 当时根本没有 boolean 这个 case——规则是空串、生成的控制器
     * 写成 'is_top' => "{$required}"（零类型约束），缺口正是从这条用例眼皮底下过去的。
     */
    public function test_tinyint_one_is_boolean_type_and_boolean_cast(): void
    {
        $column = $this->inference->describe([
            'Field' => 'is_top', 'Type' => 'tinyint(1)', 'Null' => 'NO',
            'Key' => '', 'Default' => '0', 'Extra' => '', 'Comment' => '是否置顶',
        ]);

        $this->assertSame('boolean', $column->type);
        $this->assertSame('switch', $column->formType);
        $this->assertSame('boolean', $this->inference->cast($column));
        // NOT NULL 且有默认值 '0' → sometimes|required 前缀，再加 boolean 类型规则
        $this->assertSame('sometimes|required|boolean', $this->inference->validationRule($column));
        $this->assertSame(['require', 'boolean'], $this->inference->ruleTokens($column));
    }

    /**
     * boolean / json 两个归一化类型的校验规则（延后项 #1 / 评审 I3）。
     *
     * 这两列以前在 validationRule() 与 ruleTokens() 的类型 switch 里都没有 case，规则片段是空串：
     * 生成的控制器只写 'is_hot' => "{$required}"，一条类型约束都没有，传任意字符串都能过校验，
     * 一路走到 MySQL 严格模式报 1366 → HTTP 500，而契约要求的是 422。这里钉死 NOT NULL 无默认值
     * （规则里不该出现 sometimes/nullable 前缀）与可空两种写法。
     */
    public function test_boolean_and_json_columns_get_type_validation_rules(): void
    {
        $isHot = $this->inference->describe([
            'Field' => 'is_hot', 'Type' => 'tinyint(1)', 'Null' => 'NO',
            'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '是否热门',
        ]);
        $this->assertSame('boolean', $isHot->type);
        $this->assertSame('boolean', $this->inference->validationRule($isHot));
        $this->assertSame(['require', 'boolean'], $this->inference->ruleTokens($isHot));

        $meta = $this->inference->describe([
            'Field' => 'meta', 'Type' => 'json', 'Null' => 'NO',
            'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '扩展信息',
        ]);
        $this->assertSame('json', $meta->type);
        $this->assertSame('array', $this->inference->validationRule($meta));
        $this->assertSame(['require', 'array'], $this->inference->ruleTokens($meta));

        $tags = $this->inference->describe([
            'Field' => 'tags', 'Type' => 'json', 'Null' => 'YES',
            'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '标签',
        ]);
        $this->assertSame('nullable|array', $this->inference->validationRule($tags));
        $this->assertSame(['array'], $this->inference->ruleTokens($tags));
    }

    /** 列名以 is_ 开头也走 switch，即便底层不是 tinyint(1)。 */
    public function test_is_prefixed_column_name_forces_switch_form_type(): void
    {
        $column = $this->inference->describe([
            'Field' => 'is_recommend', 'Type' => 'tinyint', 'Null' => 'NO',
            'Key' => '', 'Default' => '0', 'Extra' => '', 'Comment' => '是否推荐',
        ]);

        $this->assertSame('switch', $column->formType);
    }

    /** json 类型：cast 为 array，本身不落在夹具表内，单独覆盖。 */
    public function test_json_column_casts_to_array(): void
    {
        $column = $this->inference->describe([
            'Field' => 'extra', 'Type' => 'json', 'Null' => 'YES',
            'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '扩展字段',
        ]);

        $this->assertSame('json', $column->type);
        $this->assertSame('array', $this->inference->cast($column));
        $this->assertSame('nullable|array', $this->inference->validationRule($column));
    }

    /**
     * 交叉校验：ruleTokens() 与 validationRule() 对同一列必须互相吻合——
     * 有 max: 就必须有 length，有 in: 就必须有 invalid，反之亦然。逐列断言，
     * 覆盖全部 17 列（这张表也是 lang.stub/controller.stub 消息键的唯一真源）。
     */
    public function test_rule_tokens_agree_with_validation_rule_for_every_golden_column(): void
    {
        $columns = $this->describedColumns();

        foreach ($columns as $name => $column) {
            $rule = $this->inference->validationRule($column);
            $tokens = $this->inference->ruleTokens($column);
            // 按 | 切成规则片段再逐段判断前缀：不能直接 str_contains($rule, 'in:')，
            // 'min:0' 这个片段本身就以子串形式包含 "in:"（m-i-n-:-0），会把 sort 误判成
            // 含有 in: 规则。
            $segments = $rule === '' ? [] : explode('|', $rule);

            $hasMax = array_any($segments, static fn (string $segment): bool => str_starts_with($segment, 'max:'));
            $hasLength = in_array('length', $tokens, true);
            $this->assertSame($hasMax, $hasLength, "{$name}: max: 与 length token 不一致（rule={$rule}）");

            $hasIn = array_any($segments, static fn (string $segment): bool => str_starts_with($segment, 'in:'));
            $hasInvalid = in_array('invalid', $tokens, true);
            $this->assertSame($hasIn, $hasInvalid, "{$name}: in: 与 invalid token 不一致（rule={$rule}）");
        }
    }

    /** content 是 nullable|string 里的 "text" 类型分支：nullable 不产出 token，text 类型也不在 switch 里产出 token，容易被漏。 */
    public function test_rule_tokens_for_content_column_is_empty(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame([], $this->inference->ruleTokens($columns['content']));
    }

    public function test_rule_tokens_for_published_at_column_is_just_date(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame(['date'], $this->inference->ruleTokens($columns['published_at']));
    }

    public function test_rule_tokens_for_status_and_sort_match_their_fixed_override_rules(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame(['require', 'integer', 'invalid'], $this->inference->ruleTokens($columns['status']));
        $this->assertSame(['require', 'integer', 'min'], $this->inference->ruleTokens($columns['sort']));
    }

    /** unique 列的类型/长度 token 照常产出，只是没有唯一性相关的 token（本来就没有这一种）。 */
    public function test_rule_tokens_for_unique_column_matches_its_type_and_length_rule(): void
    {
        $columns = $this->describedColumns();

        $this->assertSame(['require', 'length'], $this->inference->ruleTokens($columns['slug']));
    }

    public function test_rule_tokens_include_require_for_not_null_columns_regardless_of_default(): void
    {
        $columns = $this->describedColumns();

        // title：NOT NULL 且无默认值（必填部分由模板的 {$required} 拼，但 create 场景下
        // 运行时最终还是会应用 required 规则，所以仍然要有 require token，否则校验失败
        // 时对应的 lang 键缺失，界面直接回显键名）。
        $this->assertContains('require', $this->inference->ruleTokens($columns['title']));

        // view_count：NOT NULL 但有默认值（validationRule() 直接产出 sometimes|required）。
        $this->assertContains('require', $this->inference->ruleTokens($columns['view_count']));

        // created_by：nullable，不应该有 require token。
        $this->assertNotContains('require', $this->inference->ruleTokens($columns['created_by']));
    }

    public function test_rule_tokens_append_email_when_name_contains_email(): void
    {
        $column = $this->inference->describe([
            'Field' => 'contact_email', 'Type' => 'varchar(100)', 'Null' => 'YES',
            'Key' => '', 'Default' => null, 'Extra' => '', 'Comment' => '联系邮箱',
        ]);

        $this->assertSame(['length', 'email'], $this->inference->ruleTokens($column));
    }
}
