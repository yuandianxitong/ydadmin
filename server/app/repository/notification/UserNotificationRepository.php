<?php
declare(strict_types=1);

namespace app\repository\notification;

use app\model\notification\UserNotification;
use core\base\Repository;
use think\facade\Db;
use think\Model;

class UserNotificationRepository extends Repository
{
    protected function getModel(): Model
    {
        return new UserNotification();
    }

    /**
     * 获取用户消息列表（user_id = 指定用户 OR user_id = 0 广播）
     */
    public function getUserMessages(int $userId, int $page, int $limit): array
    {
        $query = UserNotification::where('deleted_at', null)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->whereOr('user_id', 0);
            });

        $total = $query->count();

        // 获取已读通知ID
        $readIds = Db::table('user_notification_reads')
            ->where('user_id', $userId)
            ->whereNotNull('read_at')
            ->column('notification_id');

        $list = $query->order('created_at desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        // 标记已读状态
        foreach ($list as &$item) {
            $item['is_read'] = in_array($item['id'], $readIds);
        }

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int) ceil($total / max($limit, 1)),
            ],
        ];
    }

    /**
     * 获取未读消息数量
     */
    public function getUnreadCount(int $userId): int
    {
        $readIds = Db::table('user_notification_reads')
            ->where('user_id', $userId)
            ->whereNotNull('read_at')
            ->column('notification_id');

        $query = UserNotification::where('deleted_at', null)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->whereOr('user_id', 0);
            });

        if (!empty($readIds)) {
            $query->whereNotIn('id', $readIds);
        }

        return $query->count();
    }

    /**
     * 标记指定通知为已读（批量操作，避免 N+1）
     */
    public function markAsRead(int $userId, array $notificationIds): void
    {
        if (empty($notificationIds)) {
            return;
        }

        $notificationIds = array_map('intval', $notificationIds);
        $now = date('Y-m-d H:i:s');

        // 一次查询获取已有记录
        $existingRows = Db::table('user_notification_reads')
            ->where('user_id', $userId)
            ->whereIn('notification_id', $notificationIds)
            ->column('notification_id,read_at', 'notification_id');

        // 批量更新已有但未读的记录
        $needUpdateIds = [];
        foreach ($existingRows as $nid => $row) {
            if (empty($row['read_at'])) {
                $needUpdateIds[] = $nid;
            }
        }
        if (!empty($needUpdateIds)) {
            Db::table('user_notification_reads')
                ->where('user_id', $userId)
                ->whereIn('notification_id', $needUpdateIds)
                ->update(['read_at' => $now]);
        }

        // 批量插入不存在的记录
        $existingNids = array_keys($existingRows);
        $newNids = array_diff($notificationIds, $existingNids);
        if (!empty($newNids)) {
            $insertData = [];
            foreach ($newNids as $nid) {
                $insertData[] = [
                    'notification_id' => $nid,
                    'user_id'         => $userId,
                    'read_at'         => $now,
                    'created_at'      => $now,
                ];
            }
            Db::table('user_notification_reads')->insertAll($insertData);
        }
    }

    /**
     * 全部标记已读（批量操作，避免 N+1）
     */
    public function markAllAsRead(int $userId): void
    {
        // 获取所有已读的通知ID
        $readNotificationIds = Db::table('user_notification_reads')
            ->where('user_id', $userId)
            ->whereNotNull('read_at')
            ->column('notification_id');

        // 查询所有该用户未读的通知
        $unreadQuery = UserNotification::where('deleted_at', null)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->whereOr('user_id', 0);
            });

        if (!empty($readNotificationIds)) {
            $unreadQuery->whereNotIn('id', $readNotificationIds);
        }

        $unreadIds = $unreadQuery->column('id');

        if (empty($unreadIds)) {
            return;
        }

        // 利用 markAsRead 的批量逻辑
        $this->markAsRead($userId, $unreadIds);
    }
}
