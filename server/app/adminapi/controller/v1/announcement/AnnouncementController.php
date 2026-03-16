<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\announcement;

use core\base\Controller;
use core\attribute\Permission;
use app\service\announcement\AnnouncementService;
use app\adminapi\validate\v1\announcement\AnnouncementValidate;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '公告管理', description: '公告的增删改查、状态管理')]
class AnnouncementController extends Controller
{
    protected AnnouncementService $announcementService;

    /**
     * 公告列表
     */
    #[Permission('announcement.list')]
    #[OA\Get(
        path: '/announcement/list',
        summary: '获取公告列表',
        security: [['bearerAuth' => []]],
        tags: ['公告管理'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'type', in: 'query', description: '公告类型', schema: new OA\Schema(type: 'string')),
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
            'type'      => '',
            'keyword'   => '',
        ]);
        $result = $this->announcementService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 公告详情
     */
    #[Permission('announcement.detail')]
    #[OA\Get(
        path: '/announcement/detail/{id}',
        summary: '获取公告详情',
        security: [['bearerAuth' => []]],
        tags: ['公告管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '公告ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->announcementService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建公告
     */
    #[Permission('announcement.create')]
    #[OA\Post(
        path: '/announcement',
        summary: '创建公告',
        security: [['bearerAuth' => []]],
        tags: ['公告管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'content'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: '公告标题'),
                    new OA\Property(property: 'content', type: 'string', description: '公告内容'),
                    new OA\Property(property: 'type', type: 'string', description: '公告类型'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                    new OA\Property(property: 'sort', type: 'integer', description: '排序'),
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
        $data = $this->request->only(['title', 'content', 'type', 'status', 'sort']);
        $this->validate($data, AnnouncementValidate::class, [], false, 'create');
        $data['admin_id'] = $this->getUserId();
        $result = $this->announcementService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新公告
     */
    #[Permission('announcement.update')]
    #[OA\Put(
        path: '/announcement/{id}',
        summary: '更新公告',
        security: [['bearerAuth' => []]],
        tags: ['公告管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '公告ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: '公告标题'),
                    new OA\Property(property: 'content', type: 'string', description: '公告内容'),
                    new OA\Property(property: 'type', type: 'string', description: '公告类型'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                    new OA\Property(property: 'sort', type: 'integer', description: '排序'),
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
        $data = $this->request->only(['title', 'content', 'type', 'status', 'sort']);
        $this->validate($data, AnnouncementValidate::class, [], false, 'update');
        $this->announcementService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 更新公告状态
     */
    #[Permission('announcement.status')]
    #[OA\Put(
        path: '/announcement/{id}/status',
        summary: '更新公告状态',
        security: [['bearerAuth' => []]],
        tags: ['公告管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '公告ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '状态更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function updateStatus(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->announcementService->updateStatus($id, $status);
        return $this->success(lang('messages.operation_success'));
    }

    /**
     * 删除公告
     */
    #[Permission('announcement.delete')]
    #[OA\Delete(
        path: '/announcement/{id}',
        summary: '删除公告',
        security: [['bearerAuth' => []]],
        tags: ['公告管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '公告ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->announcementService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
