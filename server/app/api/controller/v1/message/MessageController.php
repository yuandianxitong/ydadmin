<?php
declare(strict_types=1);

namespace app\api\controller\v1\message;

use core\base\Controller;
use app\service\notification\UserNotificationService;
use think\Response;

class MessageController extends Controller
{
    protected UserNotificationService $notificationService;

    /**
     * 消息列表
     */
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

    /**
     * 未读消息数量
     */
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

    /**
     * 标记消息已读
     * ids 为空时全部标记已读，否则标记指定消息
     */
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
