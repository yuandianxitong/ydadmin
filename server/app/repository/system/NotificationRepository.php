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
 * 个人侧（mine / unread-count / read / read-all）只面向「已发布的全员广播」：status=1、target_type=1、未删除
 * （spec §1.1-7：M1 只支持全员广播，指定用户通知在 M4 实现）。已读状态记在 notification_reads，
 * 唯一键 (notification_id, admin_id)，按管理员隔离。
 */
class NotificationRepository extends Repository
{
    /** target_type：全员广播。 */
    public const TARGET_ALL = 1;

    /** read-all 的多行 upsert 每条语句最多写这么多行。 */
    private const UPSERT_CHUNK = 500;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at'];

    protected function getModel(): Model
    {
        return new Notification();
    }

    /**
     * 管理端列表：每行含 reads_count（已读人数）。keyword 模糊匹配标题（% 与 _ 按字面匹配），type 精确过滤。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getListWithReadsCount(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->withCount(['reads' => static function (Builder $reads): void {
            $reads->whereNotNull('notification_reads.read_at');
        }]);

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($keyword));
        }
        if (isset($params['type']) && $params['type'] !== '') {
            $query->where($this->qualify('type'), (int) $params['type']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'created_at desc, id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 「我的通知」：已发布的全员广播，每行附 is_read（该管理员是否已读）。$isRead 为 0/1 时按已读状态过滤。
     *
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getMine(int $adminId, ?int $isRead, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->broadcastQuery();
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

    /** 该管理员未读的已发布广播数。 */
    public function countUnread(int $adminId): int
    {
        return $this->broadcastQuery()->whereNotExists($this->readBy($adminId))->count();
    }

    /** 通知对个人侧是否可见（已发布的全员广播、未删除）。 */
    public function isVisibleBroadcast(int $id): bool
    {
        return $this->broadcastQuery()->where($this->qualify('id'), $id)->exists();
    }

    /** 标记一条已读：幂等 upsert，已读过的保留第一次的 read_at。 */
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
     * 把该管理员全部未读的已发布广播标记为已读。已有行但 read_at 为空的补上时间，没有行的插入新行；
     * 用多行 upsert，每条语句最多 UPSERT_CHUNK 行。返回本次标记的条数。
     */
    public function markAllRead(int $adminId): int
    {
        $ids = array_values(array_map(
            'intval',
            $this->broadcastQuery()->whereNotExists($this->readBy($adminId))->pluck($this->qualify('id'))->all()
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
     * 已发布、全员广播、未删除（SoftDeletes 作用域）的通知。
     *
     * @return Builder<Model>
     */
    private function broadcastQuery(): Builder
    {
        return $this->query()
            ->where($this->qualify('status'), 1)
            ->where($this->qualify('target_type'), self::TARGET_ALL);
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
