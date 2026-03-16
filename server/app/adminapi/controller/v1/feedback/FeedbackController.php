<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\feedback;

use core\base\Controller;
use core\attribute\Permission;
use app\service\feedback\FeedbackService;
use app\adminapi\validate\v1\feedback\FeedbackValidate;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '用户反馈', description: '用户反馈的查看、回复、关闭、删除管理')]
class FeedbackController extends Controller
{
    protected FeedbackService $feedbackService;

    /**
     * 反馈列表
     */
    #[Permission('feedback.list')]
    #[OA\Get(
        path: '/feedback/list',
        summary: '获取反馈列表',
        security: [['bearerAuth' => []]],
        tags: ['用户反馈'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'status', in: 'query', description: '状态', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', description: '反馈类型', schema: new OA\Schema(type: 'string')),
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
        $result = $this->feedbackService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 反馈详情
     */
    #[Permission('feedback.detail')]
    #[OA\Get(
        path: '/feedback/detail/{id}',
        summary: '获取反馈详情',
        security: [['bearerAuth' => []]],
        tags: ['用户反馈'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '反馈ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->feedbackService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 回复反馈
     */
    #[Permission('feedback.reply')]
    #[OA\Post(
        path: '/feedback/reply',
        summary: '回复反馈',
        security: [['bearerAuth' => []]],
        tags: ['用户反馈'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id', 'reply'],
                properties: [
                    new OA\Property(property: 'id', type: 'integer', description: '反馈ID'),
                    new OA\Property(property: 'reply', type: 'string', description: '回复内容'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '操作成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function reply(): Response
    {
        $data = $this->request->only(['id', 'reply']);
        $this->validate($data, FeedbackValidate::class, [], false, 'reply');
        $adminId = $this->getUserId();
        $this->feedbackService->reply((int) $data['id'], $adminId, $data['reply']);
        return $this->success(lang('messages.operation_success'));
    }

    /**
     * 关闭反馈
     */
    #[Permission('feedback.close')]
    #[OA\Post(
        path: '/feedback/close/{id}',
        summary: '关闭反馈',
        security: [['bearerAuth' => []]],
        tags: ['用户反馈'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '反馈ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '操作成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function close(): Response
    {
        $id = (int) $this->request->param('id');
        $this->feedbackService->close($id);
        return $this->success(lang('messages.operation_success'));
    }

    /**
     * 删除反馈
     */
    #[Permission('feedback.delete')]
    #[OA\Delete(
        path: '/feedback/{id}',
        summary: '删除反馈',
        security: [['bearerAuth' => []]],
        tags: ['用户反馈'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '反馈ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->feedbackService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
