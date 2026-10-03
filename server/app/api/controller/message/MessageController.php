<?php

declare(strict_types=1);

namespace app\api\controller\message;

use app\api\controller\AuthenticatedController;
use app\service\message\UserNotificationService;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * C 端站内信（M6b spec §4.7）。全部端点挂 ApiAuthMiddleware，身份取自 $request->userId；
 * C 端没有权限体系，方法一律 #[PermissionSkip]。
 *
 *   GET  /api/message/list           list         分页（page_no/page_size，兼容 page/limit），只含本人，id 倒序
 *   GET  /api/message/unread-count   unreadCount  {count}
 *   POST /api/message/read           read         {ids?: int[]}；缺省、null 或 [] → 本人全部未读；返回 []
 */
#[RouteGroup('/api/message')]
class MessageController extends AuthenticatedController
{
    #[Inject]
    protected UserNotificationService $userNotificationService;

    #[Get('/list')]
    #[PermissionSkip]
    public function list(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userNotificationService->getList((int) $request->userId, $page, $limit));
    }

    #[Get('/unread-count')]
    #[PermissionSkip]
    public function unreadCount(Request $request): Response
    {
        return $this->success(['count' => $this->userNotificationService->unreadCount((int) $request->userId)], lang('messages.get_success'));
    }

    #[Post('/read')]
    #[PermissionSkip]
    public function read(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->readRules());
        $ids = isset($data['ids']) && is_array($data['ids'])
            ? array_values(array_map('intval', $data['ids']))
            : null;
        $this->userNotificationService->markRead((int) $request->userId, $ids);

        return $this->success([], lang('messages.mark_read_success'));
    }

    /** @return array<string, string> */
    private function readRules(): array
    {
        return [
            'ids'   => 'nullable|array|max:500',
            'ids.*' => 'integer|min:1',
        ];
    }
}
