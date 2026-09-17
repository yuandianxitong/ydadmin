<?php

declare(strict_types=1);

namespace app\repository\message;

use app\model\message\UserNotification;
use core\base\Model;
use core\base\Repository;
use Illuminate\Database\Query\Builder as QueryBuilder;
use support\Db;

/**
 * 会员站内信仓储（M6b spec §2.3、§4.7）。不设 $dataScoped：C 端归属隔离靠每个方法显式带 user_id（spec §2.5，红线 Test32/35）。
 */
class UserNotificationRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new UserNotification();
    }

    /**
     * 本人站内信，id 倒序；每行附 is_read。
     *
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginateForUser(int $userId, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->where($this->qualify('user_id'), $userId);

        $total = (clone $query)->count();
        // C 端行的键集合固定（spec §4.7；Task 7 锁定），不带 user_id / updated_at
        $columns = array_map(fn (string $c): string => $this->qualify($c), ['id', 'title', 'content', 'type', 'biz_id', 'extra', 'created_at']);
        $query->select($columns);
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();

        $ids = array_map('intval', array_column($list, 'id'));
        $readIds = $ids === [] ? [] : array_map('intval', Db::table('user_notification_reads')
            ->where('user_id', $userId)
            ->whereIn('notification_id', $ids)
            ->pluck('notification_id')
            ->all());
        foreach ($list as &$row) {
            $row['is_read'] = in_array((int) $row['id'], $readIds, true);
        }
        unset($row);

        return $this->buildPagination($list, $page, $limit, $total);
    }

    public function countUnreadForUser(int $userId): int
    {
        return $this->query()
            ->where($this->qualify('user_id'), $userId)
            ->whereNotExists($this->readBy($userId))
            ->count();
    }

    /**
     * 过滤出属于该会员的 id（去重、升序）；他人的、不存在的、非正数的一律丢弃。
     *
     * @param array<int, mixed> $ids
     * @return list<int>
     */
    public function filterOwnedIds(int $userId, array $ids): array
    {
        $candidates = array_values(array_unique(array_filter(
            array_map('intval', array_filter($ids, 'is_numeric')),
            static fn (int $id): bool => $id > 0
        )));
        if ($candidates === []) {
            return [];
        }

        $owned = array_map('intval', $this->query()
            ->where($this->qualify('user_id'), $userId)
            ->whereIn($this->qualify('id'), $candidates)
            ->pluck($this->qualify('id'))
            ->all());
        sort($owned);

        return $owned;
    }

    /** @return list<int> 该会员全部未读 id，升序 */
    public function unreadIdsForUser(int $userId): array
    {
        $query = $this->query()
            ->where($this->qualify('user_id'), $userId)
            ->whereNotExists($this->readBy($userId));
        $query->orderBy($this->qualify('id'));

        return array_values(array_map('intval', $query->pluck($this->qualify('id'))->all()));
    }

    /** 「该会员已读」的 EXISTS 子查询。 */
    private function readBy(int $userId): \Closure
    {
        $notificationId = $this->qualify('id');

        return static function (QueryBuilder $sub) use ($userId, $notificationId): void {
            $sub->selectRaw('1')->from('user_notification_reads')
                ->whereColumn('user_notification_reads.notification_id', $notificationId)
                ->where('user_notification_reads.user_id', $userId);
        };
    }
}
