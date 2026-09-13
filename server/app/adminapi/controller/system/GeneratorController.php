<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\GeneratorService;
use core\base\Controller;
use core\generator\GeneratorRequest;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 代码生成器（spec §4）：
 *   GET  /adminapi/system/generator/tables             tables    system.generator.list
 *   GET  /adminapi/system/generator/columns?table=xxx  columns   system.generator.list
 *   POST /adminapi/system/generator/preview            preview   system.generator.generate
 *   POST /adminapi/system/generator/generate           generate  system.generator.generate
 *
 * 两个写端点的入参逐字相同，共用 generatorRequest()；真正的安全判定（生产禁用、名称白名单、
 * 表名白名单）都在 GeneratorService 里，控制器这层的校验只是第一道。
 */
class GeneratorController extends Controller
{
    #[Inject]
    protected GeneratorService $generatorService;

    #[Permission('system.generator.list')]
    public function tables(): Response
    {
        return $this->success($this->generatorService->getTables(), lang('messages.get_success'));
    }

    #[Permission('system.generator.list')]
    public function columns(Request $request): Response
    {
        $data = $this->validate(['table' => $request->get('table')], $this->columnRules(), $this->columnMessages());

        return $this->success($this->generatorService->getColumns((string) $data['table']), lang('messages.get_success'));
    }

    #[Permission('system.generator.generate')]
    public function preview(Request $request): Response
    {
        return $this->success($this->generatorService->preview($this->generatorRequest($request)), lang('messages.get_success'));
    }

    #[Permission('system.generator.generate')]
    public function generate(Request $request): Response
    {
        return $this->success($this->generatorService->generate($this->generatorRequest($request)), lang('messages.create_success'));
    }

    /**
     * 入参 → GeneratorRequest。validate() 的返回值就是字段白名单：columns 只声明了
     * name 与四个可编辑字段的规则，别的键根本不会出现在返回值里（spec §9.4 的第一道防线；
     * 第二道在 GeneratorService::columnsOf()，那里一切以实时查表结果为准）。
     */
    private function generatorRequest(Request $request): GeneratorRequest
    {
        $data = $this->validate($this->body($request), $this->generatorRules(), $this->generatorMessages());

        $overrides = [];
        foreach ((array) ($data['columns'] ?? []) as $column) {
            if (!is_array($column) || !isset($column['name'])) {
                continue;
            }
            $overrides[(string) $column['name']] = [
                'form_type'  => (string) $column['form_type'],
                'searchable' => (bool) $column['searchable'],
                'in_list'    => (bool) $column['in_list'],
                'in_form'    => (bool) $column['in_form'],
            ];
        }

        return new GeneratorRequest(
            (string) $data['table_name'],
            (string) $data['module_name'],
            (string) $data['model_name'],
            (string) ($data['table_comment'] ?? ''),
            $overrides,
        );
    }

    /**
     * preview 与 generate 的入参逐字相同（契约 §4.3 / §4.4）。规则数组是字面量、键是字段名、
     * 值是字符串规则，不做任何动态拼接（spec §14：M2b 的 OpenAPI 推导器要能机器读懂）。
     * 两条正则与 not_in 清单须同 GeneratorService::MODULE_PATTERN / MODEL_PATTERN / RESERVED_MODULES（spec §9.1）。
     *
     * @return array<string, string>
     */
    private function generatorRules(): array
    {
        return [
            'table_name'           => 'required|string|max:64',
            'module_name'          => 'required|string|regex:/^[a-z][a-z0-9_]{0,30}$/|not_in:admin_log,auth,business,messages,validation,generator',
            'model_name'           => 'required|string|regex:/^[A-Z][A-Za-z0-9]{0,40}$/',
            'table_comment'        => 'nullable|string|max:100|regex:/^[^\r\n<>]*$/u',
            'columns'              => 'nullable|array',
            'columns.*.name'       => 'required|string|max:64',
            'columns.*.form_type'  => 'required|string|in:input,textarea,number,switch,select,datepicker,image',
            'columns.*.searchable' => 'required|boolean',
            'columns.*.in_list'    => 'required|boolean',
            'columns.*.in_form'    => 'required|boolean',
        ];
    }

    /** @return array<string, string> 值是 lang key 时由 ValidatorFactory 翻译，否则原样返回 */
    private function generatorMessages(): array
    {
        return [
            'table_name.required'  => 'generator.table_require',
            'module_name.required' => 'generator.invalid_module_name',
            'module_name.regex'    => 'generator.invalid_module_name',
            'module_name.not_in'   => 'generator.module_name_reserved',
            'model_name.required'  => 'generator.invalid_model_name',
            'model_name.regex'     => 'generator.invalid_model_name',
            'table_comment.regex'  => 'generator.invalid_table_comment',
            'table_comment.max'    => 'generator.invalid_table_comment',
        ];
    }

    /** @return array<string, string> */
    private function columnRules(): array
    {
        return [
            'table' => 'required|string|max:64',
        ];
    }

    /** @return array<string, string> */
    private function columnMessages(): array
    {
        return [
            'table.required' => 'generator.table_require',
            'table.string'   => 'generator.table_require',
            'table.max'      => 'generator.table_require',
        ];
    }
}
