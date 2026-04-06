<?php
declare(strict_types=1);

namespace app\repository\system;

use core\base\Model;
use core\base\Repository;
use app\model\system\Notification;
use app\model\system\NotificationRead;
use think\facade\Db;

class NotificationRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Notification();
    }

    /**
     * 获取通知列表（管理端，含已读统计）
     */
    public function getListWithStats(array $where, int $page, int $limit): array
    {
        $query = $this->model->where('deleted_at', null);

        if (!empty($where['keyword'])) {
            $query->where('title', 'like', '%' . $where['keyword'] . '%');
        }
        if (isset($where['type']) && $where['type'] !== '') {
            $query->where('type', (int) $where['type']);
        }

        $total = $query->count();
        $list = $query->withCount(['reads'])
            ->order('created_at desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $limit),
            ]
        ];
    }

    /**
     * 获取用户通知列表（前台）
     */
    public function getUserNotifications(int $adminId, array $where, int $page, int $limit): array
    {
        $query = $this->model->where('deleted_at', null)
            ->where('status', 1)
            ->where(function ($q) use ($adminId) {
                $q->where('target_type', 1)
                  ->whereOr(function ($sub) use ($adminId) {
                      $sub->where('target_type', 2)
                          ->whereExists(function ($exists) use ($adminId) {
                              $exists->table('notification_reads')
                                  ->where('notification_reads.notification_id', Db::raw('notifications.id'))
                                  ->where('notification_reads.admin_id', $adminId);
                          });
                  });
            });

        if (isset($where['is_read'])) {
            if ((int) $where['is_read'] === 1) {
                $query->whereExists(function ($q) use ($adminId) {
                    $q->table('notification_reads')
                      ->where('notification_reads.notification_id', Db::raw('notifications.id'))
                      ->where('notification_reads.admin_id', $adminId)
                      ->whereNotNull('read_at');
                });
            } else {
                $query->whereNotExists(function ($q) use ($adminId) {
                    $q->table('notification_reads')
                      ->where('notification_reads.notification_id', Db::raw('notifications.id'))
                      ->where('notification_reads.admin_id', $adminId)
                      ->whereNotNull('read_at');
                });
            }
        }

        $total = $query->count();
        $list = $query->order('created_at desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        // 标记已读状态
        $readIds = NotificationRead::where('admin_id', $adminId)
            ->whereNotNull('read_at')
            ->column('notification_id');

        foreach ($list as &$item) {
            $item['is_read'] = in_array($item['id'], $readIds);
        }

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $limit),
            ]
        ];
    }

    /**
     * 获取未读数量
     */
    public function getUnreadCount(int $adminId): int
    {
        $readIds = NotificationRead::where('admin_id', $adminId)
            ->whereNotNull('read_at')
            ->column('notification_id');

        $query = $this->model->where('deleted_at', null)
            ->where('status', 1)
            ->where('target_type', 1);

        if (!empty($readIds)) {
            $query->whereNotIn('id', $readIds);
        }

        return $query->count();
    }

    /**
     * 标记单条已读（基于 uk_notification_admin 唯一索引的幂等 upsert，单条 SQL）
     */
    public function markAsRead(int $notificationId, int $adminId): void
    {
        $now = date('Y-m-d H:i:s');
        Db::execute(
            'INSERT INTO notification_reads (notification_id, admin_id, read_at, created_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE read_at = IFNULL(read_at, VALUES(read_at))',
            [$notificationId, $adminId, $now, $now]
        );
    }

    /**
     * 全部标记已读（批量 upsert，固定 3 条 SQL 无论未读数量多少）
     */
    public function markAllAsRead(int $adminId): void
    {
        // 1. 一次性取出该用户所有未读的广播通知 ID
        $readNotificationIds = NotificationRead::where('admin_id', $adminId)
            ->whereNotNull('read_at')
            ->column('notification_id');

        $unreadQuery = $this->model->where('deleted_at', null)
            ->where('status', 1)
            ->where('target_type', 1);

        if (!empty($readNotificationIds)) {
            $unreadQuery->whereNotIn('id', $readNotificationIds);
        }

        $unreadIds = $unreadQuery->column('id');

        if (empty($unreadIds)) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 2. 一次 UPDATE 将已存在但 read_at 为空的记录置为已读
        NotificationRead::where('admin_id', $adminId)
            ->whereIn('notification_id', $unreadIds)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        // 3. 找出还没有对应 notification_read 记录的通知 ID，批量 insert
        $existingIds = NotificationRead::where('admin_id', $adminId)
            ->whereIn('notification_id', $unreadIds)
            ->column('notification_id');

        $missingIds = array_diff($unreadIds, $existingIds);
        if (!empty($missingIds)) {
            $rows = [];
            foreach ($missingIds as $nid) {
                $rows[] = [
                    'notification_id' => $nid,
                    'admin_id'        => $adminId,
                    'read_at'         => $now,
                    'created_at'      => $now,
                ];
            }
            (new NotificationRead())->insertAll($rows);
        }
    }
}
