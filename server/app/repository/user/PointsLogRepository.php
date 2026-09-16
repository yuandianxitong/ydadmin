<?php

declare(strict_types=1);

namespace app\repository\user;

use app\model\user\PointsLog;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 积分流水仓储（points_logs 表，spec §8）：不设 $dataScoped，理由同 BalanceLogRepository。
 */
class PointsLogRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'created_at'];

    protected function getModel(): Model
    {
        return new PointsLog();
    }

    /**
     * C 端：只本人流水，按创建时间倒序。
     *
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getUserList(int $userId, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->where($this->qualify('user_id'), $userId);
        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('id'), 'desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 管理端：keyword（会员昵称/手机号模糊）、type、start_date、end_date（闭区间，按天）；
     * 行含 user_nickname（join users）。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getManageList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()
            ->join('users', 'users.id', '=', $this->qualify('user_id'))
            ->select([$this->qualify('*'), 'users.nickname as user_nickname']);

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = Like::contains($keyword);
            $query->where(function ($q) use ($like): void {
                $q->where('users.nickname', 'like', $like)->orWhere('users.mobile', 'like', $like);
            });
        }
        if (isset($params['type']) && $params['type'] !== '') {
            $query->where($this->qualify('type'), (int) $params['type']);
        }
        if (!empty($params['start_date'])) {
            $query->where($this->qualify('created_at'), '>=', $params['start_date'] . ' 00:00:00');
        }
        if (!empty($params['end_date'])) {
            $query->where($this->qualify('created_at'), '<=', $params['end_date'] . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('id'), 'desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
