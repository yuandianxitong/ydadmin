<?php

declare(strict_types=1);

namespace app\repository\message;

use app\model\message\UserNotificationRead;
use core\base\Model;
use core\base\Repository;
use support\Db;

/**
 * 会员站内信已读仓储（M6b spec §2.4）。唯一键 (notification_id, user_id)：INSERT IGNORE 幂等，已读过的保留第一次 read_at。
 * 不校验归属——调用方先经 UserNotificationRepository::filterOwnedIds() 过滤（Task 7）。
 */
class UserNotificationReadRepository extends Repository
{
    /** 每条多行 INSERT 最多这么多行。 */
    private const INSERT_CHUNK = 500;

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new UserNotificationRead();
    }

    /**
     * @param array<int, int> $notificationIds
     * @return int 新增的已读行数
     */
    public function markRead(int $userId, array $notificationIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $notificationIds)));
        if ($ids === []) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        foreach (array_chunk($ids, self::INSERT_CHUNK) as $chunk) {
            $rows = array_map(static fn (int $id): array => [
                'notification_id' => $id,
                'user_id'         => $userId,
                'read_at'         => $now,
            ], $chunk);
            $inserted += Db::table('user_notification_reads')->insertOrIgnore($rows);
        }

        return $inserted;
    }
}
