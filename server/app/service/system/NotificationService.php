<?php
declare(strict_types=1);

namespace app\service\system;

use core\base\Service;
use app\repository\system\NotificationRepository;

class NotificationService extends Service
{
    protected NotificationRepository $repo;

    /**
     * 获取通知列表（管理端）
     */
    public function getNotificationList(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 20);
        return $this->repo->getListWithStats($params, $page, $limit);
    }

    /**
     * 获取通知详情
     */
    public function getNotificationDetail(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * 创建通知
     */
    public function createNotification(array $data): array
    {
        return $this->repo->create($data);
    }

    /**
     * 更新通知
     */
    public function updateNotification(int $id, array $data): bool
    {
        $notification = $this->repo->find($id);
        if (!$notification) {
            $this->throwBusinessException(lang('business.notification_not_found'));
        }
        return $this->repo->update($id, $data);
    }

    /**
     * 删除通知
     */
    public function deleteNotification(int $id): bool
    {
        $notification = $this->repo->find($id);
        if (!$notification) {
            $this->throwBusinessException(lang('business.notification_not_found'));
        }
        return $this->repo->delete($id);
    }

    /**
     * 获取用户通知
     */
    public function getUserNotifications(int $adminId, array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 20);
        return $this->repo->getUserNotifications($adminId, $params, $page, $limit);
    }

    /**
     * 获取未读数量
     */
    public function getUnreadCount(int $adminId): int
    {
        return $this->repo->getUnreadCount($adminId);
    }

    /**
     * 标记已读
     */
    public function markAsRead(int $notificationId, int $adminId): void
    {
        $this->repo->markAsRead($notificationId, $adminId);
    }

    /**
     * 全部标记已读
     */
    public function markAllAsRead(int $adminId): void
    {
        $this->repo->markAllAsRead($adminId);
    }
}
