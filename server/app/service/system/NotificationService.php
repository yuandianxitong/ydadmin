<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\NotificationRepository;
use core\base\Service;
use core\context\RequestContext;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 站内通知（契约 §2.10，spec §6.3）。不受数据权限约束。
 *
 * - 管理侧：增删改查；发送人一律取当前管理员（RequestContext::actingUser()），请求体里的 sender_id 不接收。
 * - 个人侧：只面向已发布的全员广播。指定用户通知（target_type=2）在控制器校验层就返回 422，M4 实现。
 * - 当前管理员一律取 RequestContext::actingUser()。
 */
class NotificationService extends Service
{
    #[Inject]
    protected NotificationRepository $notificationRepository;

    /**
     * @param array<string, mixed> $params keyword（标题模糊）、type
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getNotificationList(array $params, int $page, int $limit): array
    {
        return $this->notificationRepository->getListWithReadsCount($params, $page, $limit);
    }

    /**
     * 详情。不存在时抛 business.notification_not_found（code 400，契约 §2.10 如此）。
     *
     * @return array<string, mixed>
     */
    public function getNotificationDetail(int $id): array
    {
        return $this->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 已校验：title、content、type 必有
     * @return array<string, mixed>
     */
    public function createNotification(array $data): array
    {
        return $this->notificationRepository->create([
            'title'       => $data['title'],
            'content'     => $data['content'],
            'type'        => (int) $data['type'],
            'target_type' => (int) ($data['target_type'] ?? NotificationRepository::TARGET_ALL),
            'status'      => (int) ($data['status'] ?? 1),
            'sender_id'   => RequestContext::actingUser() ?: null,
        ]);
    }

    /** @param array<string, mixed> $data 字段均可选 */
    public function updateNotification(int $id, array $data): void
    {
        $this->findOrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip(['title', 'content', 'type', 'target_type', 'status'])),
            static fn ($value) => $value !== null
        );
        if ($update !== []) {
            $this->notificationRepository->update($id, $update);
        }
    }

    /** 删除（软删）。已读记录保留，个人侧查询按 SoftDeletes 自动排除。 */
    public function deleteNotification(int $id): void
    {
        $this->findOrFail($id);
        $this->notificationRepository->delete($id);
    }

    /**
     * 我的通知。$params['is_read'] 为 '0'/'1' 时按已读状态过滤，缺省或空串不过滤。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getMyNotifications(array $params, int $page, int $limit): array
    {
        $raw = $params['is_read'] ?? null;
        $isRead = $raw === null || $raw === '' ? null : (int) $raw;

        return $this->notificationRepository->getMine(RequestContext::actingUser(), $isRead, $page, $limit);
    }

    public function getUnreadCount(): int
    {
        return $this->notificationRepository->countUnread(RequestContext::actingUser());
    }

    /** 标记已读。通知对个人侧不可见（草稿、指定用户、已删除、不存在）时抛 business.notification_not_found，不写已读记录。 */
    public function markAsRead(int $id): void
    {
        if (!$this->notificationRepository->isVisibleBroadcast($id)) {
            throw new BusinessException(lang('business.notification_not_found'));
        }
        $this->notificationRepository->markRead($id, RequestContext::actingUser());
    }

    /** 全部标记已读，只影响当前管理员；返回本次标记的条数。 */
    public function markAllAsRead(): int
    {
        return $this->notificationRepository->markAllRead(RequestContext::actingUser());
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->notificationRepository->find($id) ?? throw new BusinessException(lang('business.notification_not_found'));
    }
}
