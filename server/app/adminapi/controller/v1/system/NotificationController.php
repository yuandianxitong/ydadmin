<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use core\base\Controller;
use app\service\system\NotificationService;
use app\adminapi\validate\v1\system\NotificationValidate;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '通知公告', description: '通知的发布、查看、已读管理')]
class NotificationController extends Controller
{
    protected NotificationService $service;

    #[Permission('system.notification.list')]
    #[OA\Get(
        path: '/system/notification',
        summary: '通知列表（管理端）',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        parameters: [
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', description: '通知类型', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index()
    {
        $params = $this->getRequestData([
            'keyword' => '',
            'type'    => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $result = $this->service->getNotificationList($params);
        return $this->paginate($result);
    }

    #[Permission('system.notification.list')]
    #[OA\Get(
        path: '/system/notification/{id}',
        summary: '通知详情',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '通知ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '通知不存在', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function show()
    {
        $id = (int) $this->request->param('id');
        $result = $this->service->getNotificationDetail($id);
        if (!$result) {
            return $this->error(lang('business.notification_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.notification.create')]
    #[OA\Post(
        path: '/system/notification',
        summary: '创建通知',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'content', 'type'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: '通知标题'),
                    new OA\Property(property: 'content', type: 'string', description: '通知内容'),
                    new OA\Property(property: 'type', type: 'string', description: '通知类型'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '发布成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function store()
    {
        $data = $this->request->post();
        $this->validate($data, NotificationValidate::class, [], 'create');
        $data['sender_id'] = $this->getUserId();
        $result = $this->service->createNotification($data);
        return $this->success(lang('messages.publish_success'), $result);
    }

    #[Permission('system.notification.update')]
    #[OA\Put(
        path: '/system/notification/{id}',
        summary: '更新通知',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '通知ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'title', type: 'string', description: '通知标题'),
                new OA\Property(property: 'content', type: 'string', description: '通知内容'),
                new OA\Property(property: 'type', type: 'string', description: '通知类型'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update()
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, NotificationValidate::class, [], 'update');
        $this->service->updateNotification($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.notification.delete')]
    #[OA\Delete(
        path: '/system/notification/{id}',
        summary: '删除通知',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '通知ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete()
    {
        $id = (int) $this->request->param('id');
        $this->service->deleteNotification($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/notification/mine',
        summary: '我的通知列表',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        parameters: [
            new OA\Parameter(name: 'is_read', in: 'query', description: '是否已读', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function mine()
    {
        $params = $this->getRequestData([
            'is_read' => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $adminId = $this->getUserId();
        $result = $this->service->getUserNotifications($adminId, $params);
        return $this->paginate($result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/notification/unread-count',
        summary: '未读通知数量',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function unreadCount()
    {
        $adminId = $this->getUserId();
        $count = $this->service->getUnreadCount($adminId);
        return $this->success(lang('messages.get_success'), ['count' => $count]);
    }

    #[PermissionSkip]
    #[OA\Post(
        path: '/system/notification/{id}/read',
        summary: '标记通知为已读',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '通知ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '标记成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function read()
    {
        $id = (int) $this->request->param('id');
        $adminId = $this->getUserId();
        $this->service->markAsRead($id, $adminId);
        return $this->success(lang('messages.mark_read_success'));
    }

    #[PermissionSkip]
    #[OA\Post(
        path: '/system/notification/read-all',
        summary: '全部标记为已读',
        security: [['bearerAuth' => []]],
        tags: ['通知公告'],
        responses: [
            new OA\Response(response: 200, description: '标记成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function readAll()
    {
        $adminId = $this->getUserId();
        $this->service->markAllAsRead($adminId);
        return $this->success(lang('messages.mark_all_read_success'));
    }
}
