<?php
declare(strict_types=1);

namespace app\api\controller\v1\feedback;

use core\base\Controller;
use app\service\feedback\FeedbackService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '意见反馈', description: '用户反馈提交与查询')]
class FeedbackController extends Controller
{
    protected FeedbackService $feedbackService;

    #[OA\Post(
        path: '/feedback/submit',
        summary: '提交反馈',
        security: [['bearerAuth' => []]],
        tags: ['意见反馈'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', description: '反馈类型'),
                    new OA\Property(property: 'content', type: 'string', description: '反馈内容'),
                    new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string'), description: '图片URL列表'),
                    new OA\Property(property: 'contact', type: 'string', description: '联系方式'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '提交成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function submit(): Response
    {
        try {
            $data = $this->request->only(['type', 'content', 'images', 'contact']);

            if (empty($data['content'])) {
                return $this->error(lang('validation.content_require'));
            }

            $userId = $this->getUserId();
            $result = $this->feedbackService->submit($userId, $data);
            return $this->success(lang('messages.submit_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/feedback/list',
        summary: '我的反馈列表',
        security: [['bearerAuth' => []]],
        tags: ['意见反馈'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse')),
        ]
    )]
    public function list(): Response
    {
        try {
            $params = $this->request->only(['page_no', 'page_size']);
            $userId = $this->getUserId();
            $result = $this->feedbackService->getUserList($userId, $params);
            return $this->paginate($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/feedback/detail/{id}',
        summary: '反馈详情',
        security: [['bearerAuth' => []]],
        tags: ['意见反馈'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function detail(int $id): Response
    {
        try {
            $result = $this->feedbackService->detail($id);
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
