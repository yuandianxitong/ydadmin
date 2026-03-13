<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\CodeGeneratorService;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '代码生成器', description: '从数据表自动生成CRUD代码')]
class CodeGeneratorController extends Controller
{
    protected CodeGeneratorService $generatorService;

    #[Permission('system.generator.list')]
    #[OA\Get(
        path: '/system/generator/tables',
        summary: '获取数据表列表',
        security: [['bearerAuth' => []]],
        tags: ['代码生成器'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function tables(): Response
    {
        $tables = $this->generatorService->getTables();
        return $this->success(lang('messages.get_success'), $tables);
    }

    #[Permission('system.generator.list')]
    #[OA\Get(
        path: '/system/generator/columns',
        summary: '获取表字段信息',
        security: [['bearerAuth' => []]],
        tags: ['代码生成器'],
        parameters: [
            new OA\Parameter(name: 'table', in: 'query', required: true, description: '数据表名', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function columns(): Response
    {
        $tableName = $this->request->param('table', '');
        if (empty($tableName)) {
            return $this->error(lang('business.please_specify_table'));
        }

        $columns = $this->generatorService->getTableColumns($tableName);
        return $this->success(lang('messages.get_success'), $columns);
    }

    #[Permission('system.generator.generate')]
    #[OA\Post(
        path: '/system/generator/preview',
        summary: '预览生成的代码',
        security: [['bearerAuth' => []]],
        tags: ['代码生成器'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['table_name', 'module_name', 'model_name'],
                properties: [
                    new OA\Property(property: 'table_name', type: 'string', description: '数据表名'),
                    new OA\Property(property: 'module_name', type: 'string', description: '模块名'),
                    new OA\Property(property: 'model_name', type: 'string', description: '模型名'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '预览成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function preview(): Response
    {
        $config = $this->request->post();

        if (empty($config['table_name']) || empty($config['module_name']) || empty($config['model_name'])) {
            return $this->error(lang('business.please_fill_config'));
        }

        $files = $this->generatorService->generate($config);
        return $this->success(lang('messages.preview_success'), $files);
    }

    #[Permission('system.generator.generate')]
    #[OA\Post(
        path: '/system/generator/generate',
        summary: '生成并写入文件',
        security: [['bearerAuth' => []]],
        tags: ['代码生成器'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['table_name', 'module_name', 'model_name'],
                properties: [
                    new OA\Property(property: 'table_name', type: 'string', description: '数据表名'),
                    new OA\Property(property: 'module_name', type: 'string', description: '模块名'),
                    new OA\Property(property: 'model_name', type: 'string', description: '模型名'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '生成成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function generate(): Response
    {
        $config = $this->request->post();

        if (empty($config['table_name']) || empty($config['module_name']) || empty($config['model_name'])) {
            return $this->error(lang('business.please_fill_config'));
        }

        $files = $this->generatorService->generate($config);
        $result = $this->generatorService->writeFiles($files);

        return $this->success(lang('messages.generate_success'), [
            'files'  => $result,
            'route'  => $files['route']['content'] ?? '',
        ]);
    }
}
