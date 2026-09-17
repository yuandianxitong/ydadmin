<?php

declare(strict_types=1);

namespace app\service\message;

use app\repository\message\UserNotificationReadRepository;
use app\repository\message\UserNotificationRepository;
use core\base\Service;
use DI\Attribute\Inject;

/**
 * C 端站内信（M6b spec §4.7）。一切查询都按传入的 userId 显式过滤（表不接数据权限，身份来自 $request->userId）。
 *
 * 标记已读：
 * - ids 为 null 或 []（调用方没指定）→ 本人全部未读；
 * - 否则只处理属于本人的 id，其余（他人的、不存在的）静默忽略；
 * - 调用方给了 id 但去掉非正整数后为空时，什么都不做，不能退化成「全部已读」；
 * - 写入是 INSERT IGNORE（唯一键 notification_id + user_id），重复标记幂等，已读时间不重写。
 *
 * 容器单例，无实例态。
 */
class UserNotificationService extends Service
{
    #[Inject]
    protected UserNotificationRepository $userNotificationRepository;

    #[Inject]
    protected UserNotificationReadRepository $userNotificationReadRepository;

    /** @return array{list: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}} */
    public function getList(int $userId, int $page, int $limit): array
    {
        return $this->userNotificationRepository->paginateForUser($userId, $page, $limit);
    }

    public function unreadCount(int $userId): int
    {
        return $this->userNotificationRepository->countUnreadForUser($userId);
    }

    /** @param list<int>|null $ids null 或空 → 本人全部未读 */
    public function markRead(int $userId, ?array $ids): void
    {
        if ($ids === null || $ids === []) {
            $targets = $this->userNotificationRepository->unreadIdsForUser($userId);
        } else {
            $requested = array_values(array_unique(array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )));
            if ($requested === []) {
                return;
            }
            $targets = $this->userNotificationRepository->filterOwnedIds($userId, $requested);
        }

        if ($targets === []) {
            return;
        }

        $this->userNotificationReadRepository->markRead($userId, $targets);
    }
}
