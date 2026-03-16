<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\message;

use core\base\Controller;
use app\service\message\MessageService;
use core\attribute\Permission;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '消息模板', description: '消息模板的增删改查及测试发送')]
class MessageTemplateController extends Controller
{
    protected MessageService $service;

    /**
     * 模板列表
     */
    #[Permission('message.template.list')]
    #[OA\Get(
        path: '/message/template',
        summary: '获取消息模板列表',
        security: [['bearerAuth' => []]],
        tags: ['消息模板'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData([
            'keyword' => '',
            'status'  => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $result = $this->service->getTemplateList($params);
        return $this->paginate($result);
    }

    /**
     * 模板详情
     */
    #[Permission('message.template.list')]
    #[OA\Get(
        path: '/message/template/{id}',
        summary: '获取消息模板详情',
        security: [['bearerAuth' => []]],
        tags: ['消息模板'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '模板ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function show(): Response
    {
        $id = (int)$this->request->param('id');
        $result = $this->service->getTemplateDetail($id);
        if (!$result) {
            return $this->error(lang('business.template_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建模板
     */
    #[Permission('message.template.create')]
    #[OA\Post(
        path: '/message/template',
        summary: '创建消息模板',
        security: [['bearerAuth' => []]],
        tags: ['消息模板'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'name'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: '模板编码'),
                    new OA\Property(property: 'name', type: 'string', description: '模板名称'),
                    new OA\Property(property: 'content', type: 'string', description: '模板内容'),
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
            $result = $this->service->createTemplate($data);
            return $this->success(lang('messages.create_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新模板
     */
    #[Permission('message.template.update')]
    #[OA\Put(
        path: '/message/template/{id}',
        summary: '更新消息模板',
        security: [['bearerAuth' => []]],
        tags: ['消息模板'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '模板ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', description: '模板名称'),
                new OA\Property(property: 'content', type: 'string', description: '模板内容'),
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
            $this->service->updateTemplate($id, $data);
            return $this->success(lang('messages.update_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除模板
     */
    #[Permission('message.template.delete')]
    #[OA\Delete(
        path: '/message/template/{id}',
        summary: '删除消息模板',
        security: [['bearerAuth' => []]],
        tags: ['消息模板'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '模板ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $this->service->deleteTemplate($id);
            return $this->success(lang('messages.delete_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 测试发送
     */
    #[Permission('message.template.send')]
    #[OA\Post(
        path: '/message/template/test-send',
        summary: '测试发送消息模板',
        security: [['bearerAuth' => []]],
        tags: ['消息模板'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'receivers'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: '模板编码'),
                    new OA\Property(property: 'receivers', type: 'array', items: new OA\Items(type: 'string'), description: '接收者列表'),
                    new OA\Property(property: 'data', type: 'object', description: '模板变量数据'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '发送成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function testSend(): Response
    {
        try {
            $code = (string)$this->request->param('code', '');
            $receivers = $this->request->param('receivers', []);
            $data = $this->request->param('data', []);

            if (empty($code)) {
                return $this->error(lang('business.template_code_required'));
            }

            $result = $this->service->send($code, $receivers, $data);
            return $this->success(lang('messages.send_complete'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
