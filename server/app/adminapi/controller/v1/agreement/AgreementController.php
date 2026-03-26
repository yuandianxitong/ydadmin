<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\agreement;

use core\base\Controller;
use core\attribute\Permission;
use app\service\agreement\AgreementService;
use app\adminapi\validate\v1\agreement\AgreementValidate;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '协议管理', description: '协议的增删改查')]
class AgreementController extends Controller
{
    protected AgreementService $agreementService;

    /**
     * 协议列表
     */
    #[Permission('agreement.list')]
    #[OA\Get(
        path: '/agreement/list',
        summary: '获取协议列表',
        security: [['bearerAuth' => []]],
        tags: ['协议管理'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'status'    => '',
            'keyword'   => '',
        ]);
        $result = $this->agreementService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 协议详情
     */
    #[Permission('agreement.list')]
    #[OA\Get(
        path: '/agreement/detail/{id}',
        summary: '获取协议详情',
        security: [['bearerAuth' => []]],
        tags: ['协议管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '协议ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->agreementService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建协议
     */
    #[Permission('agreement.create')]
    #[OA\Post(
        path: '/agreement',
        summary: '创建协议',
        security: [['bearerAuth' => []]],
        tags: ['协议管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'code', 'content'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: '协议标题'),
                    new OA\Property(property: 'code', type: 'string', description: '协议标识码'),
                    new OA\Property(property: 'content', type: 'string', description: '协议内容'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function create(): Response
    {
        $data = $this->request->only(['title', 'code', 'content', 'status']);
        $this->validate($data, AgreementValidate::class, [], false, 'create');
        $result = $this->agreementService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新协议
     */
    #[Permission('agreement.update')]
    #[OA\Put(
        path: '/agreement/{id}',
        summary: '更新协议',
        security: [['bearerAuth' => []]],
        tags: ['协议管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '协议ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: '协议标题'),
                    new OA\Property(property: 'content', type: 'string', description: '协议内容'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only(['title', 'content', 'status']);
        $this->validate($data, AgreementValidate::class, [], false, 'update');
        $this->agreementService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除协议
     */
    #[Permission('agreement.delete')]
    #[OA\Delete(
        path: '/agreement/{id}',
        summary: '删除协议',
        security: [['bearerAuth' => []]],
        tags: ['协议管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '协议ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->agreementService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
