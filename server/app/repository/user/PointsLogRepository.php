<?php
declare(strict_types=1);

namespace app\repository\user;

use app\model\user\PointsLog;
use core\base\Model;
use core\base\Repository;

class PointsLogRepository extends Repository
{
    protected function getModel(): Model
    {
        return new PointsLog();
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = PointsLog::order('id', 'desc');
        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        if (!empty($params['user_ids'])) {
            $query->whereIn('user_id', $params['user_ids']);
        }
        $startTime = $params['start_time'] ?? $params['start_date'] ?? null;
        $endTime = $params['end_time'] ?? $params['end_date'] ?? null;
        if ($startTime) {
            $query->where('created_at', '>=', $startTime);
        }
        if ($endTime) {
            $query->where('created_at', '<=', $endTime . ' 23:59:59');
        }
        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        $list = $this->enrichListWithNames($list);

        $lastPage = $limit > 0 ? (int) ceil($total / $limit) : 1;
        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
        ];
    }

    public function getUserLogs(int $userId, int $page = 1, int $limit = 10): array
    {
        $query = PointsLog::where('user_id', $userId)->order('id', 'desc');
        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        $list = $this->enrichListWithNames($list);

        $lastPage = $limit > 0 ? (int) ceil($total / $limit) : 1;
        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
        ];
    }

    /**
     * 为列表数据补充用户昵称和操作人名称
     */
    private function enrichListWithNames(array $list): array
    {
        if (empty($list)) {
            return $list;
        }

        $userIds = array_unique(array_column($list, 'user_id'));
        $operatorIds = array_unique(array_filter(array_column($list, 'operator_id')));

        $userMap = \app\model\user\User::whereIn('id', $userIds)->column('nickname', 'id');
        $operatorMap = $operatorIds ? \app\model\system\Admin::whereIn('id', $operatorIds)->column('nickname', 'id') : [];

        foreach ($list as &$item) {
            $item['user_nickname'] = $userMap[$item['user_id']] ?? '-';
            $item['operator_name'] = $item['operator_id'] ? ($operatorMap[$item['operator_id']] ?? '-') : '-';
        }
        unset($item);

        return $list;
    }
}
