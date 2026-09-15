<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminRepository;
use app\repository\system\NotificationRepository;
use core\base\Service;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\realtime\RealtimePublisher;
use DI\Attribute\Inject;

/**
 * 站内通知（契约 §2.10，spec §6.3；M4 spec §6「通知 target_type=2」）。通知本身不受数据权限约束。
 *
 * - 管理侧：增删改查；发送人一律取当前管理员（RequestContext::actingUser()），请求体里的 sender_id 不接收。
 *   指定通知（target_type=2）的 admin_ids 去重后必须全部在当前管理员的数据范围内且存在（AdminRepository::visibleIds），
 *   收件人写进 notification_reads（未读行）；target_type 发布后不能修改。
 * - 个人侧：本人可见 = 已发布 且（全员广播 或 存在本人收件行）。
 * - 推送（M4 spec §6）：发布——新建 status=1，或 status 由 0 改为 1——在事务提交后经 RealtimePublisher
 *   推 notification.created（广播 targets='all'，指定通知 targets=收件人）；编辑已发布不推送；推送失败只记日志。
 * - 当前管理员一律取 RequestContext::actingUser()。
 */
class NotificationService extends Service
{
    /** admin-options 最多返回的条数。 */
    public const ADMIN_OPTIONS_LIMIT = 50;

    #[Inject]
    protected NotificationRepository $notificationRepository;

    #[Inject]
    protected AdminRepository $adminRepository;

    #[Inject]
    protected RealtimePublisher $realtimePublisher;

    /**
     * @param array<string, mixed> $params keyword（标题模糊）、type
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getNotificationList(array $params, int $page, int $limit): array
    {
        return $this->notificationRepository->getListWithReadsCount($params, $page, $limit);
    }

    /**
     * 详情：通知行 + admin_ids（指定通知的收件人，广播为 []）。不存在时抛 business.notification_not_found（code 400）。
     *
     * @return array<string, mixed>
     */
    public function getNotificationDetail(int $id): array
    {
        $row = $this->findOrFail($id);
        $row['admin_ids'] = (int) $row['target_type'] === NotificationRepository::TARGET_ADMINS
            ? $this->notificationRepository->recipientIds($id)
            : [];

        return $row;
    }

    /**
     * @param array<string, mixed> $data 已校验：title、content、type 必有；target_type=2 时 admin_ids 必有
     * @return array<string, mixed>
     */
    public function createNotification(array $data): array
    {
        $targetType = (int) ($data['target_type'] ?? NotificationRepository::TARGET_ALL);
        $adminIds = $targetType === NotificationRepository::TARGET_ADMINS
            ? $this->assertRecipients((array) ($data['admin_ids'] ?? []))
            : [];

        return $this->runInTransaction(function () use ($data, $targetType, $adminIds): array {
            $row = $this->notificationRepository->create([
                'title'       => $data['title'],
                'content'     => $data['content'],
                'type'        => (int) $data['type'],
                'target_type' => $targetType,
                'status'      => (int) ($data['status'] ?? 1),
                'sender_id'   => RequestContext::actingUser() ?: null,
            ]);
            if ($targetType === NotificationRepository::TARGET_ADMINS) {
                $this->notificationRepository->replaceRecipients((int) $row['id'], $adminIds);
            }
            if ((int) $row['status'] === 1) {
                $this->afterCommit(fn () => $this->pushCreated($row));
            }

            return $row;
        });
    }

    /** @param array<string, mixed> $data 字段均可选 */
    public function updateNotification(int $id, array $data): void
    {
        $current = $this->findOrFail($id);
        $currentTarget = (int) $current['target_type'];
        if (isset($data['target_type']) && (int) $data['target_type'] !== $currentTarget) {
            throw new ValidationException(['target_type' => lang('validation.notification_target_immutable')]);
        }
        $adminIds = $currentTarget === NotificationRepository::TARGET_ADMINS && array_key_exists('admin_ids', $data)
            ? $this->assertRecipients((array) $data['admin_ids'])
            : null;

        $update = array_filter(
            array_intersect_key($data, array_flip(['title', 'content', 'type', 'status'])),
            static fn ($value) => $value !== null
        );

        $this->runInTransaction(function () use ($id, $current, $update, $adminIds): void {
            if ($update !== []) {
                $this->notificationRepository->update($id, $update);
            }
            if ($adminIds !== null) {
                $this->notificationRepository->replaceRecipients($id, $adminIds);
            }
            if ((int) $current['status'] === 0 && isset($update['status']) && (int) $update['status'] === 1) {
                $row = array_merge($current, $update);
                $this->afterCommit(fn () => $this->pushCreated($row));
            }
        });
    }

    /** 删除（软删）。已读记录与收件行保留，个人侧查询按 SoftDeletes 自动排除。 */
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

    /** 标记已读。通知对本人不可见（草稿、非收件人的指定通知、已删除、不存在）时抛 business.notification_not_found，不写已读记录。 */
    public function markAsRead(int $id): void
    {
        $adminId = RequestContext::actingUser();
        if (!$this->notificationRepository->isVisibleTo($id, $adminId)) {
            throw new BusinessException(lang('business.notification_not_found'));
        }
        $this->notificationRepository->markRead($id, $adminId);
    }

    /** 全部标记已读，只影响当前管理员可见的通知；返回本次标记的条数。 */
    public function markAllAsRead(): int
    {
        return $this->notificationRepository->markAllRead(RequestContext::actingUser());
    }

    /**
     * 「指定管理员」下拉：当前数据范围内启用的管理员，最多 ADMIN_OPTIONS_LIMIT 条。
     *
     * @return list<array{id: int, username: string, nickname: string}>
     */
    public function adminOptions(string $keyword): array
    {
        return $this->adminRepository->options($keyword, self::ADMIN_OPTIONS_LIMIT);
    }

    /**
     * admin_ids 去重转整数后，必须全部在当前数据范围内且存在（visibleIds 经 query() 的数据权限作用域，并排除软删）。
     *
     * @param array<int, mixed> $adminIds
     * @return list<int>
     */
    private function assertRecipients(array $adminIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $adminIds)));
        if ($ids === [] || count($this->adminRepository->visibleIds($ids)) !== count($ids)) {
            throw new ValidationException(['admin_ids' => lang('validation.notification_admin_ids_invalid')]);
        }

        return $ids;
    }

    /**
     * 推 notification.created：广播给全体在线管理员，指定通知只给收件人（取提交后的收件行）。
     * RealtimePublisher::publish() 自身吞掉异常只记 warning，推送失败不影响已提交的业务。
     *
     * @param array<string, mixed> $row 通知行（至少含 id、title、type、target_type、created_at）
     */
    private function pushCreated(array $row): void
    {
        $id = (int) $row['id'];
        $targets = (int) $row['target_type'] === NotificationRepository::TARGET_ADMINS
            ? $this->notificationRepository->recipientIds($id)
            : 'all';
        if ($targets === []) {
            return;
        }

        $this->realtimePublisher->publish($targets, 'notification.created', [
            'id'         => $id,
            'title'      => (string) $row['title'],
            'type'       => (int) $row['type'],
            'created_at' => (string) $row['created_at'],
        ]);
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->notificationRepository->find($id) ?? throw new BusinessException(lang('business.notification_not_found'));
    }
}
