<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\GeneratorService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 代码生成器（spec §4）。本任务只落地前两个只读端点：
 *   GET /adminapi/system/generator/tables             tables   system.generator.list
 *   GET /adminapi/system/generator/columns?table=xxx  columns  system.generator.list
 * preview/generate 两个写端点由后续任务在本类补齐，权限点 system.generator.generate（菜单 201 已种）。
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
        $data = $this->validate(['table' => $request->get('table')], $this->generatorRules(), $this->messages());

        return $this->success($this->generatorService->getColumns((string) $data['table']), lang('messages.get_success'));
    }

    /** @return array<string, string> */
    private function generatorRules(): array
    {
        return [
            'table' => 'required|string|max:64',
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'table.required' => 'generator.table_require',
            'table.string'   => 'generator.table_require',
            'table.max'      => 'generator.table_require',
        ];
    }
}
