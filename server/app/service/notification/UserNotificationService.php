<?php
declare(strict_types=1);

namespace app\service\notification;

use core\base\Service;
use app\repository\notification\UserNotificationRepository;

class UserNotificationService extends Service
{
    protected UserNotificationRepository $repo;

    /**
     * 获取用户消息列表
     */
    public function getUserMessages(int $userId, array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 10);

        return $this->repo->getUserMessages($userId, $page, $limit);
    }

    /**
     * 获取未读消息数量
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->repo->getUnreadCount($userId);
    }

    /**
     * 标记指定通知为已读
     */
    public function markAsRead(int $userId, array $notificationIds): void
    {
        $this->repo->markAsRead($userId, $notificationIds);
    }

    /**
     * 全部标记已读
     */
    public function markAllAsRead(int $userId): void
    {
        $this->repo->markAllAsRead($userId);
    }

    /**
     * 创建用户通知
     */
    public function createNotification(array $data): array
    {
        $notification = $this->repo->create($data);

        $this->trigger('user.notification.created', [
            'notification_id' => $notification['id'],
            'user_id'         => $data['user_id'] ?? 0,
            'type'            => $data['type'] ?? 'system',
        ]);

        return $notification;
    }
}
