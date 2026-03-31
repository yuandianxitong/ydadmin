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
     * 标记已读
     */
    public function markAsRead(int $notificationId, int $adminId): void
    {
        $exists = NotificationRead::where('notification_id', $notificationId)
            ->where('admin_id', $adminId)
            ->find();

        if ($exists) {
            if (!$exists->read_at) {
                $exists->read_at = date('Y-m-d H:i:s');
                $exists->save();
            }
        } else {
            NotificationRead::create([
                'notification_id' => $notificationId,
                'admin_id'        => $adminId,
                'read_at'         => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * 全部标记已读
     */
    public function markAllAsRead(int $adminId): void
    {
        // 获取所有未读的通知
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

        $now = date('Y-m-d H:i:s');
        foreach ($unreadIds as $nid) {
            $exists = NotificationRead::where('notification_id', $nid)
                ->where('admin_id', $adminId)
                ->find();

            if ($exists) {
                $exists->read_at = $now;
                $exists->save();
            } else {
                NotificationRead::create([
                    'notification_id' => $nid,
                    'admin_id'        => $adminId,
                    'read_at'         => $now,
                ]);
            }
        }
    }
}
