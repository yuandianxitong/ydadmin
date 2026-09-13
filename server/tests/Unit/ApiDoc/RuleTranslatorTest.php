<?php

declare(strict_types=1);

namespace tests\Unit\ApiDoc;

use core\apidoc\RuleTranslator;
use tests\TestCase;

final class RuleTranslatorTest extends TestCase
{
    /**
     * 全表唯一有技术含量处：max/min 必须先扫完整串定型，再回头解释，不能单趟从左到右。
     * 'nullable|max:50|string'（type 在 max 之后）与 'nullable|string|max:50'（type 在 max 之前）
     * 必须翻译出完全相同的 schema。
     */
    public function test_max_and_min_are_polymorphic_regardless_of_token_order(): void
    {
        $translator = new RuleTranslator();

        $typeAfterMax = $translator->translate('nullable|max:50|string');
        $typeBeforeMax = $translator->translate('nullable|string|max:50');

        $expected = ['type' => 'string', 'nullable' => true, 'maxLength' => 50];
        $this->assertSame($expected, $typeAfterMax['schema'], 'max 在 string 之前时被错误解释');
        $this->assertSame($expected, $typeBeforeMax['schema'], 'max 在 string 之后时被错误解释');
        $this->assertFalse($typeAfterMax['required']);
        $this->assertFalse($typeBeforeMax['required']);
        $this->assertSame([], $typeAfterMax['notes']);
        $this->assertSame([], $typeBeforeMax['notes']);
    }

    /** integer 上 max/min → maximum/minimum；array 上 → maxItems/minItems。 */
    public function test_max_and_min_on_integer_and_array(): void
    {
        $translator = new RuleTranslator();

        // MenuController::menuRules 'sort' 字段
        $sort = $translator->translate('sometimes|required|integer|min:0');
        $this->assertSame(['type' => 'integer', 'minimum' => 0], $sort['schema']);

        // DictionaryController::batchDelete 内联规则 'ids'
        $ids = $translator->translate('required|array|min:1');
        $this->assertSame(['type' => 'array', 'minItems' => 1], $ids['schema']);
        $this->assertTrue($ids['required']);
    }

    /**
     * sometimes 与 required 谁赢：sometimes 一旦出现，required 就不再生效，不管 token 顺序。
     * 'sometimes|required|...' 是本仓库更新场景的固定写法（DictionaryController::dictionaryRules 等）。
     */
    public function test_sometimes_always_wins_over_required(): void
    {
        $translator = new RuleTranslator();

        // RoleController::updateRules 里 status 字段
        $status = $translator->translate('sometimes|required|integer|in:0,1');
        $this->assertFalse($status['required'], 'sometimes|required 组合必须落到 required=false（局部更新语义）');

        // MenuController::updateRules 'role_ids'：sometimes 单独出现，没有 required 陪同
        $roleIds = $translator->translate('sometimes|array');
        $this->assertFalse($roleIds['required']);

        // DictionaryController::dictionaryRules('create') 'name' 字段：没有 sometimes，required 正常生效
        $name = $translator->translate('required|string|max:100');
        $this->assertTrue($name['required']);
    }

    /** present → 进 required，同时 nullable: true（spec §7）。 */
    public function test_present_is_required_and_nullable(): void
    {
        $translator = new RuleTranslator();

        // RoleController::storeRules 'menu_ids'
        $menuIds = $translator->translate('present|array');
        $this->assertTrue($menuIds['required']);
        $this->assertSame(['type' => 'array', 'nullable' => true], $menuIds['schema']);

        // SystemConfigController 内联规则 'config_value'：present 单独出现，没有类型 token
        $configValue = $translator->translate('present');
        $this->assertTrue($configValue['required']);
        $this->assertSame(['nullable' => true], $configValue['schema']);
    }

    /** in 的枚举值到达时是字符串；类型已定型为 integer 才转型，否则保持字符串。 */
    public function test_in_enum_casts_by_settled_type(): void
    {
        $translator = new RuleTranslator();

        // GeneratorController::previewRules 'schedule'（合成字段名，规则串取自真实用法）
        $stringEnum = $translator->translate('required|string|in:day,week,month');
        $this->assertSame(['day', 'week', 'month'], $stringEnum['schema']['enum']);

        // RoleController 'data_scope'
        $intEnum = $translator->translate('sometimes|required|integer|in:1,2,3,4,5');
        $this->assertSame([1, 2, 3, 4, 5], $intEnum['schema']['enum']);

        // LogController::loginLogRules 'login_result'：没有 string/integer/array 任何类型 token
        $untypedEnum = $translator->translate('nullable|in:0,1');
        $this->assertSame(['0', '1'], $untypedEnum['schema']['enum'], '类型未定型时枚举值保持字符串，不猜类型');
    }

    /** regex：剥掉 PHP 定界符（含修饰符）写进 pattern，原始串原样进 notes。 */
    public function test_regex_strips_delimiters_and_keeps_original_string_in_notes(): void
    {
        $translator = new RuleTranslator();

        // GeneratorController::previewRules 'model_name'
        $modelName = $translator->translate('required|string|regex:/^[A-Z][A-Za-z0-9]{0,40}$/');
        $this->assertSame('^[A-Z][A-Za-z0-9]{0,40}$', $modelName['schema']['pattern']);
        $this->assertSame(['regex:/^[A-Z][A-Za-z0-9]{0,40}$/'], $modelName['notes']);

        // AdminController::adminRules 'mobile'
        $mobile = $translator->translate('nullable|regex:/^1[3-9]\d{9}$/');
        $this->assertSame('^1[3-9]\d{9}$', $mobile['schema']['pattern']);

        // GeneratorController::previewRules 'table_comment'：u 修饰符没有 OpenAPI 对应物，必须原样留痕
        $tableComment = $translator->translate('nullable|string|max:100|regex:/^[^\r\n<>]*$/u');
        $this->assertSame('^[^\r\n<>]*$', $tableComment['schema']['pattern']);
        $this->assertSame(['regex:/^[^\r\n<>]*$/u'], $tableComment['notes']);
    }

    /** not_in 只进 notes：Swagger UI 把 not:{enum:[...]} 渲染得很差。 */
    public function test_not_in_never_becomes_a_schema_keyword(): void
    {
        $translator = new RuleTranslator();

        // NotificationController::storeRules 'target_type'
        $result = $translator->translate('sometimes|required|integer|in:1,2|not_in:2');

        $this->assertArrayNotHasKey('not', $result['schema']);
        $this->assertSame([1, 2], $result['schema']['enum']);
        $this->assertSame(['not_in:2'], $result['notes']);

        // GeneratorController::generateRules 'module_name'：regex 与 not_in 组合，notes 按 token 出现顺序累积
        $moduleName = $translator->translate('required|string|regex:/^[a-z][a-z0-9_]{0,30}$/|not_in:admin_log,auth,business,messages,validation,generator');
        $this->assertSame(
            ['regex:/^[a-z][a-z0-9_]{0,30}$/', 'not_in:admin_log,auth,business,messages,validation,generator'],
            $moduleName['notes']
        );
    }

    /** required_if 不进 required：OpenAPI 的 required 是静态列表，宁可说"可选"也不要反过来骗人。 */
    public function test_required_if_is_never_required_but_condition_goes_to_notes(): void
    {
        $translator = new RuleTranslator();

        // MenuController::menuRules 'name'
        $name = $translator->translate('nullable|string|max:100|required_if:type,2');
        $this->assertFalse($name['required']);
        $this->assertSame(['required_if:type,2'], $name['notes']);
    }

    /** alpha_dash → pattern（本仓库只用 alpha_dash:ascii 这一种写法）。 */
    public function test_alpha_dash_produces_a_pattern(): void
    {
        $translator = new RuleTranslator();

        // DictionaryController::dictionaryRules('create') 'code'
        $code = $translator->translate('required|string|max:100|alpha_dash:ascii');
        $this->assertSame('^[A-Za-z0-9_-]+$', $code['schema']['pattern']);
    }

    /**
     * 格式类 token（email/url/alpha_dash/regex/date_format）在没有显式 string/integer/array/boolean
     * token 时把类型兜底为 string——它们本来就只在字符串上有意义。三条断言都是本仓库现存的真实规则串：
     * 不兜底的话，第一条会把一条真实的 maxLength 约束丢进 notes（静默少说也是一种不准确），
     * 后两条会产出连 type 都没有的空 schema。
     */
    public function test_format_tokens_imply_string_type_when_no_explicit_type_present(): void
    {
        $translator = new RuleTranslator();

        // AdminController::adminRules / DepartmentController::departmentRules 'email'
        $email = $translator->translate('nullable|email|max:100');
        $this->assertSame(
            ['type' => 'string', 'nullable' => true, 'format' => 'email', 'maxLength' => 100],
            $email['schema']
        );
        $this->assertFalse($email['required']);
        $this->assertSame([], $email['notes']);

        // MenuController::menuRules 'external_link'
        $url = $translator->translate('nullable|url');
        $this->assertSame(['type' => 'string', 'nullable' => true, 'format' => 'uri'], $url['schema']);
        $this->assertSame([], $url['notes']);

        // AdminController::adminRules 'mobile'
        $mobile = $translator->translate('nullable|regex:/^1[3-9]\d{9}$/');
        $this->assertSame(
            ['type' => 'string', 'nullable' => true, 'pattern' => '^1[3-9]\d{9}$'],
            $mobile['schema']
        );
        $this->assertSame(['regex:/^1[3-9]\d{9}$/'], $mobile['notes']);
    }

    /** 显式类型 token 永远优先于格式类 token 的隐含类型：不会被 regex/email/url 等拉回 string。 */
    public function test_explicit_type_wins_over_format_implied_type(): void
    {
        $translator = new RuleTranslator();

        $result = $translator->translate('integer|regex:/^\d+$/');

        $this->assertSame('integer', $result['schema']['type']);
        $this->assertSame('^\d+$', $result['schema']['pattern']);
    }

    /** date_format 按格式串里是否含时间字符（H/h/G/g/i/s）判 date 还是 date-time；同样隐含 string。 */
    public function test_date_format_maps_to_date_or_date_time(): void
    {
        $translator = new RuleTranslator();

        // LogController::loginLogRules 'start_date'（本仓库现存的唯一真实写法）
        $dateOnly = $translator->translate('nullable|date_format:Y-m-d');
        $this->assertSame('string', $dateOnly['schema']['type']);
        $this->assertSame('date', $dateOnly['schema']['format']);

        // 合成串：本仓库目前没有 date-time 用例，但翻译器必须能识别时间分量
        $dateTime = $translator->translate('nullable|date_format:Y-m-d H:i:s');
        $this->assertSame('date-time', $dateTime['schema']['format']);
    }

    /** 未知规则：原样进 notes 并且绝不能让整条翻译崩掉——这是本里程碑要治的"静默说谎"。 */
    public function test_unknown_rule_is_recorded_verbatim_and_never_dropped(): void
    {
        $translator = new RuleTranslator();

        $result = $translator->translate('required|string|confirmed');

        $this->assertSame(['type' => 'string'], $result['schema']);
        $this->assertTrue($result['required']);
        $this->assertSame(['confirmed'], $result['notes']);
    }
}
