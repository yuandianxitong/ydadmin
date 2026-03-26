<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\wechat;

use core\base\Controller;
use app\service\wechat\AutoReplyService;
use core\attribute\Permission;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '自动回复', description: '微信公众号自动回复规则管理')]
class AutoReplyController extends Controller
{
    protected AutoReplyService $service;

    /**
     * 自动回复列表
     */
    #[Permission('channel.official.auto_reply')]
    #[OA\Get(
        path: '/wechat/auto-reply',
        summary: '获取自动回复列表',
        security: [['bearerAuth' => []]],
        tags: ['自动回复'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'type', in: 'query', description: '回复类型', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData([
            'type'    => '',
            'status'  => '',
            'keyword' => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $result = $this->service->getList($params);
        return $this->paginate($result);
    }

    /**
     * 自动回复详情
     */
    #[Permission('channel.official.auto_reply')]
    #[OA\Get(
        path: '/wechat/auto-reply/{id}',
        summary: '获取自动回复详情',
        security: [['bearerAuth' => []]],
        tags: ['自动回复'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '自动回复ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function show(): Response
    {
        $id = (int)$this->request->param('id');
        $result = $this->service->getDetail($id);
        if (!$result) {
            return $this->error(lang('business.auto_reply_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建自动回复
     */
    #[Permission('channel.official.auto_reply.create')]
    #[OA\Post(
        path: '/wechat/auto-reply',
        summary: '创建自动回复规则',
        security: [['bearerAuth' => []]],
        tags: ['自动回复'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'content'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', description: '回复类型'),
                    new OA\Property(property: 'keyword', type: 'string', description: '触发关键词'),
                    new OA\Property(property: 'content', type: 'string', description: '回复内容'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function store(): Response
    {
        try {
            $data = $this->request->post();
            $result = $this->service->create($data);
            return $this->success(lang('messages.create_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新自动回复
     */
    #[Permission('channel.official.auto_reply.update')]
    #[OA\Put(
        path: '/wechat/auto-reply/{id}',
        summary: '更新自动回复规则',
        security: [['bearerAuth' => []]],
        tags: ['自动回复'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '自动回复ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'type', type: 'string', description: '回复类型'),
                new OA\Property(property: 'keyword', type: 'string', description: '触发关键词'),
                new OA\Property(property: 'content', type: 'string', description: '回复内容'),
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $data = $this->request->post();
            $this->service->update($id, $data);
            return $this->success(lang('messages.update_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除自动回复
     */
    #[Permission('channel.official.auto_reply.delete')]
    #[OA\Delete(
        path: '/wechat/auto-reply/{id}',
        summary: '删除自动回复规则',
        security: [['bearerAuth' => []]],
        tags: ['自动回复'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '自动回复ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $this->service->delete($id);
            return $this->success(lang('messages.delete_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
