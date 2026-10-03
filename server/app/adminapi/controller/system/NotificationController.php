<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\NotificationService;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 站内通知（契约 §2.10；M4 spec §6 指定管理员）。
 *
 * 端点（具名路由在 {id} 通配路由之前注册）：
 *   GET    /adminapi/system/notification                 index         system.notification.list
 *   GET    /adminapi/system/notification/mine            mine          PermissionSkip
 *   GET    /adminapi/system/notification/unread-count    unreadCount   PermissionSkip
 *   GET    /adminapi/system/notification/admin-options   adminOptions  system.notification.create
 *   POST   /adminapi/system/notification/read-all        readAll       PermissionSkip
 *   POST   /adminapi/system/notification/{id}/read       read          PermissionSkip
 *   GET    /adminapi/system/notification/{id}            show          system.notification.list
 *   POST   /adminapi/system/notification                 store         system.notification.create
 *   PUT    /adminapi/system/notification/{id}            update        system.notification.update
 *   DELETE /adminapi/system/notification/{id}            delete        system.notification.delete
 *
 * target_type：1 全员广播，2 指定管理员（admin_ids 必填，范围与存在性由服务层判定）；发布后不能修改。
 */
#[RouteGroup('/adminapi/system/notification')]
class NotificationController extends AuthenticatedController
{
    #[Inject]
    protected NotificationService $notificationService;

    #[Get('')]
    #[Permission('system.notification.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->notificationService->getNotificationList((array) $request->get(), $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('system.notification.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->notificationService->getNotificationDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('system.notification.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->notificationService->createNotification($data), lang('messages.publish_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('system.notification.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->notificationService->updateNotification((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('system.notification.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->notificationService->deleteNotification((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Get('/mine')]
    #[PermissionSkip]
    public function mine(Request $request): Response
    {
        $params = $this->validate((array) $request->get(), $this->mineRules(), [
            'is_read.in' => 'validation.notification_is_read_invalid',
        ]);
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->notificationService->getMyNotifications($params, $page, $limit));
    }

    #[Get('/unread-count')]
    #[PermissionSkip]
    public function unreadCount(): Response
    {
        return $this->success(['count' => $this->notificationService->getUnreadCount()], lang('messages.get_success'));
    }

    #[Get('/admin-options')]
    #[Permission('system.notification.create')]
    public function adminOptions(Request $request): Response
    {
        $params = $this->validate((array) $request->get(), $this->adminOptionsRules(), [
            'keyword.string' => 'validation.notification_keyword_max',
            'keyword.max'    => 'validation.notification_keyword_max',
        ]);

        return $this->success($this->notificationService->adminOptions((string) ($params['keyword'] ?? '')), lang('messages.get_success'));
    }

    #[Post('/{id:\d+}/read')]
    #[PermissionSkip]
    public function read(Request $request, string $id): Response
    {
        $this->notificationService->markAsRead((int) $id);

        return $this->success([], lang('messages.mark_read_success'));
    }

    #[Post('/read-all')]
    #[PermissionSkip]
    public function readAll(): Response
    {
        $this->notificationService->markAllAsRead();

        return $this->success([], lang('messages.mark_all_read_success'));
    }

    /**
     * create 场景 title/content/type 必填；update 场景一律 sometimes|required（局部更新，传空字符串必须失败）。
     * admin_ids：create 场景 target_type=2 时必填；update 场景可选（只对指定通知生效，服务层判定）。
     * admin_ids 的数据范围与存在性不在这里校验（需要查库），由 NotificationService 抛 422。
     * content 列是 TEXT（65535 字节），限 10000 字符，避免超长内容在 MySQL 严格模式下 500。
     *
     * @return array<string, string>
     */
    private function rules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'title'       => "{$required}|string|max:200",
            'content'     => "{$required}|string|max:10000",
            'type'        => "{$required}|integer|in:1,2,3",
            'target_type' => 'sometimes|required|integer|in:1,2',
            'admin_ids'   => $scene === 'create' ? 'required_if:target_type,2|array|max:500' : 'sometimes|required|array|min:1|max:500',
            'admin_ids.*' => 'integer|min:1',
            'status'      => 'sometimes|required|integer|in:0,1',
        ];
    }

    /**
     * 薄包装，委派给既有的 rules('create')。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->rules('create');
    }

    /**
     * 薄包装，委派给既有的 rules('update')。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->rules('update');
    }

    /** @return array<string, string> */
    private function mineRules(): array
    {
        return ['is_read' => 'nullable|in:0,1'];
    }

    /** @return array<string, string> */
    private function adminOptionsRules(): array
    {
        return ['keyword' => 'nullable|string|max:50'];
    }

    /**
     * message 值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'title.required'       => 'validation.notification_title_require',
            'title.string'         => 'validation.notification_title_require',
            'title.max'            => 'validation.notification_title_max',
            'content.required'     => 'validation.notification_content_require',
            'content.string'       => 'validation.notification_content_require',
            'content.max'          => 'validation.notification_content_max',
            'type.required'        => 'validation.notification_type_require',
            'type.integer'         => 'validation.notification_type_invalid',
            'type.in'              => 'validation.notification_type_invalid',
            'target_type.required' => 'validation.notification_target_invalid',
            'target_type.integer'  => 'validation.notification_target_invalid',
            'target_type.in'       => 'validation.notification_target_invalid',
            'admin_ids.required_if' => 'validation.notification_admin_ids_require',
            'admin_ids.required'   => 'validation.notification_admin_ids_require',
            'admin_ids.array'      => 'validation.notification_admin_ids_invalid',
            'admin_ids.min'        => 'validation.notification_admin_ids_require',
            'admin_ids.max'        => 'validation.notification_admin_ids_max',
            'admin_ids.*.integer'  => 'validation.notification_admin_ids_invalid',
            'admin_ids.*.min'      => 'validation.notification_admin_ids_invalid',
            'status.required'      => 'validation.status_invalid',
            'status.integer'       => 'validation.status_integer',
            'status.in'            => 'validation.status_invalid',
        ];
    }
}
