<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Notification;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use support\Db;

/**
 * 站内通知仓储（不受数据权限约束）。
 *
 * 个人侧（mine / unread-count / read / read-all）面向「本人可见」的通知（M4 spec §6）：
 * status=1、未删除（SoftDeletes），且 target_type=1（全员广播）或存在本人的收件行。
 * 指定通知（target_type=2）的收件人在发布时写进 notification_reads（read_at 为 NULL 即未读）；
 * 广播的已读行照旧在「读」时按需插入。唯一键 (notification_id, admin_id)，按管理员隔离。
 */
class NotificationRepository extends Repository
{
    /** target_type：全员广播。 */
    public const TARGET_ALL = 1;

    /** target_type：指定管理员。 */
    public const TARGET_ADMINS = 2;

    /** read-all 与收件行的多行写入每条语句最多这么多行。 */
    private const UPSERT_CHUNK = 500;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at'];

    protected function getModel(): Model
    {
        return new Notification();
    }

    /**
     * 管理端列表：每行含 reads_count（已读人数）与 target_count（指定通知的收件人数，广播为 null）。
     * keyword 模糊匹配标题（% 与 _ 按字面匹配），type 精确过滤。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getListWithReadsCount(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->withCount([
            'reads' => static function (Builder $reads): void {
                $reads->whereNotNull('notification_reads.read_at');
            },
            'reads as recipients_count',
        ]);

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($keyword));
        }
        if (isset($params['type']) && $params['type'] !== '') {
            $query->where($this->qualify('type'), (int) $params['type']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'created_at desc, id desc')->forPage($page, $limit)->get()->toArray();
        foreach ($list as &$row) {
            $row['target_count'] = (int) $row['target_type'] === self::TARGET_ADMINS ? (int) $row['recipients_count'] : null;
            unset($row['recipients_count']);
        }
        unset($row);

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 「我的通知」：本人可见的通知，每行附 is_read（该管理员是否已读）。$isRead 为 0/1 时按已读状态过滤。
     *
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getMine(int $adminId, ?int $isRead, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->visibleQuery($adminId);
        if ($isRead === 1) {
            $query->whereExists($this->readBy($adminId));
        } elseif ($isRead === 0) {
            $query->whereNotExists($this->readBy($adminId));
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'created_at desc, id desc')->forPage($page, $limit)->get()->toArray();

        $readIds = $this->readNotificationIds($adminId, array_map('intval', array_column($list, 'id')));
        foreach ($list as &$row) {
            $row['is_read'] = in_array((int) $row['id'], $readIds, true);
        }
        unset($row);

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /** 该管理员可见且未读的通知数。 */
    public function countUnread(int $adminId): int
    {
        return $this->visibleQuery($adminId)->whereNotExists($this->readBy($adminId))->count();
    }

    /** 通知对该管理员的个人侧是否可见。 */
    public function isVisibleTo(int $notificationId, int $adminId): bool
    {
        return $this->visibleQuery($adminId)->where($this->qualify('id'), $notificationId)->exists();
    }

    /** 标记一条已读：幂等 upsert，已读过的保留第一次的 read_at；指定通知的未读收件行被补上时间。 */
    public function markRead(int $notificationId, int $adminId): void
    {
        $now = date('Y-m-d H:i:s');
        Db::statement(
            'INSERT INTO notification_reads (notification_id, admin_id, read_at, created_at) VALUES (?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE read_at = IFNULL(read_at, ?)',
            [$notificationId, $adminId, $now, $now, $now]
        );
    }

    /**
     * 把该管理员全部可见且未读的通知标记为已读。已有行但 read_at 为空（含指定通知的收件行）补上时间，
     * 没有行的（广播）插入新行；用多行 upsert，每条语句最多 UPSERT_CHUNK 行。返回本次标记的条数。
     */
    public function markAllRead(int $adminId): int
    {
        $ids = array_values(array_map(
            'intval',
            $this->visibleQuery($adminId)->whereNotExists($this->readBy($adminId))->pluck($this->qualify('id'))->all()
        ));
        $now = date('Y-m-d H:i:s');
        foreach (array_chunk($ids, self::UPSERT_CHUNK) as $chunk) {
            $bindings = [];
            foreach ($chunk as $id) {
                array_push($bindings, $id, $adminId, $now, $now);
            }
            $bindings[] = $now;
            Db::statement(
                'INSERT INTO notification_reads (notification_id, admin_id, read_at, created_at) VALUES '
                . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)'))
                . ' ON DUPLICATE KEY UPDATE read_at = IFNULL(read_at, ?)',
                $bindings
            );
        }

        return count($ids);
    }

    /**
     * 指定通知的收件人换成 $adminIds：插入缺失的收件行（read_at NULL），删除名单之外且未读的行；已读行保留。
     *
     * @param array<int, int> $adminIds 已去重、已校验的管理员 id
     */
    public function replaceRecipients(int $notificationId, array $adminIds): void
    {
        $adminIds = array_values(array_unique(array_map('intval', $adminIds)));
        $stale = Db::table('notification_reads')->where('notification_id', $notificationId)->whereNull('read_at');
        if ($adminIds !== []) {
            $stale->whereNotIn('admin_id', $adminIds);
        }
        $stale->delete();

        $now = date('Y-m-d H:i:s');
        foreach (array_chunk($adminIds, self::UPSERT_CHUNK) as $chunk) {
            $bindings = [];
            foreach ($chunk as $adminId) {
                array_push($bindings, $notificationId, $adminId, $now);
            }
            Db::statement(
                'INSERT INTO notification_reads (notification_id, admin_id, read_at, created_at) VALUES '
                . implode(', ', array_fill(0, count($chunk), '(?, ?, NULL, ?)'))
                . ' ON DUPLICATE KEY UPDATE notification_id = notification_id',
                $bindings
            );
        }
    }

    /** @return list<int> 该通知全部收件行的管理员 id（含已读），升序 */
    public function recipientIds(int $notificationId): array
    {
        return array_values(array_map('intval', Db::table('notification_reads')
            ->where('notification_id', $notificationId)
            ->orderBy('admin_id')
            ->pluck('admin_id')
            ->all()));
    }

    /**
     * 本人可见：已发布、未删除（SoftDeletes 作用域），且全员广播或存在本人收件行。
     *
     * @return Builder<Model>
     */
    private function visibleQuery(int $adminId): Builder
    {
        $notificationId = $this->qualify('id');
        $targetType = $this->qualify('target_type');

        return $this->query()
            ->where($this->qualify('status'), 1)
            ->where(static function (Builder $q) use ($adminId, $notificationId, $targetType): void {
                $q->where($targetType, self::TARGET_ALL)
                    ->orWhereExists(static function (QueryBuilder $sub) use ($adminId, $notificationId): void {
                        $sub->selectRaw('1')->from('notification_reads')
                            ->whereColumn('notification_reads.notification_id', $notificationId)
                            ->where('notification_reads.admin_id', $adminId);
                    });
            });
    }

    /** 「该管理员已读」的 EXISTS 子查询。 */
    private function readBy(int $adminId): \Closure
    {
        $notificationId = $this->qualify('id');

        return static function (QueryBuilder $sub) use ($adminId, $notificationId): void {
            $sub->selectRaw('1')->from('notification_reads')
                ->whereColumn('notification_reads.notification_id', $notificationId)
                ->where('notification_reads.admin_id', $adminId)
                ->whereNotNull('notification_reads.read_at');
        };
    }

    /**
     * @param array<int, int> $notificationIds
     * @return list<int> 其中该管理员已读的通知 id
     */
    private function readNotificationIds(int $adminId, array $notificationIds): array
    {
        if ($notificationIds === []) {
            return [];
        }

        return array_values(array_map('intval', Db::table('notification_reads')
            ->where('admin_id', $adminId)
            ->whereIn('notification_id', $notificationIds)
            ->whereNotNull('read_at')
            ->pluck('notification_id')
            ->all()));
    }
}
