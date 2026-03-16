<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\dataimport;

use core\base\Controller;
use core\attribute\Permission;
use app\service\dataimport\DataImportService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '数据导入', description: '文件上传导入及导入历史记录')]
class DataImportController extends Controller
{
    protected DataImportService $dataImportService;

    /**
     * 上传并导入数据
     */
    #[Permission('dataimport.upload')]
    #[OA\Post(
        path: '/dataimport/upload',
        summary: '上传并导入数据',
        security: [['bearerAuth' => []]],
        tags: ['数据导入'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file', 'module'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: '导入文件'),
                        new OA\Property(property: 'module', type: 'string', description: '导入模块'),
                        new OA\Property(property: 'field_map', type: 'string', description: '字段映射(JSON字符串)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '导入成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->error('请上传文件');
        }

        $module = $this->request->post('module', '');
        if (empty($module)) {
            return $this->error('请指定导入模块');
        }

        // 保存上传文件
        $saveName = \think\facade\Filesystem::disk('public')->putFile('imports', $file);
        $filePath = app()->getRuntimePath() . '../public/storage/' . $saveName;

        $adminId = $this->getUserId();

        // 获取字段映射（前端传入JSON字符串）
        $fieldMapJson = $this->request->post('field_map', '{}');
        $fieldMap = json_decode($fieldMapJson, true) ?: [];

        // 默认行处理器：仅验证不为空
        $rowHandler = function (array $row) {
            // 默认处理逻辑，具体模块可通过扩展覆盖
        };

        $result = $this->dataImportService->import($module, $filePath, $fieldMap, $rowHandler, $adminId);
        return $this->success(lang('messages.operation_success'), $result);
    }

    /**
     * 导入历史记录
     */
    #[Permission('dataimport.history')]
    #[OA\Get(
        path: '/dataimport/history',
        summary: '获取数据导入历史记录',
        security: [['bearerAuth' => []]],
        tags: ['数据导入'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'module', in: 'query', description: '导入模块', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function history(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'module'    => '',
        ]);
        $result = $this->dataImportService->getHistory($params);
        return $this->paginate($result);
    }
}
