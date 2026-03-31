<?php
declare(strict_types=1);

namespace app\api\controller\v1\message;

use core\base\Controller;
use app\service\notification\UserNotificationService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '消息通知', description: '用户消息列表、未读数量、标记已读')]
class MessageController extends Controller
{
    protected UserNotificationService $notificationService;

    #[OA\Get(
        path: '/message/list',
        summary: '消息列表',
        security: [['bearerAuth' => []]],
        tags: ['消息通知'],
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
            $result = $this->notificationService->getUserMessages($userId, $params);
            return $this->paginate($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/message/unread-count',
        summary: '获取未读消息数量',
        security: [['bearerAuth' => []]],
        tags: ['消息通知'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function unreadCount(): Response
    {
        try {
            $userId = $this->getUserId();
            $count = $this->notificationService->getUnreadCount($userId);
            return $this->success(lang('messages.get_success'), ['count' => $count]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/message/read',
        summary: '标记消息已读',
        security: [['bearerAuth' => []]],
        tags: ['消息通知'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), description: '消息ID列表，为空则全部标记已读'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '操作成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function read(): Response
    {
        try {
            $ids = $this->request->param('ids', []);
            $userId = $this->getUserId();

            if (empty($ids)) {
                $this->notificationService->markAllAsRead($userId);
            } else {
                $this->notificationService->markAsRead($userId, (array) $ids);
            }

            return $this->success(lang('messages.operation_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
