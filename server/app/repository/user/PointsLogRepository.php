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
        $query = PointsLog::with(['user' => function ($q) {
            $q->field('id, nickname');
        }, 'operator' => function ($q) {
            $q->field('id, nickname');
        }])->order('id', 'desc');

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

        foreach ($list as &$item) {
            $item['user_nickname'] = $item['user']['nickname'] ?? '-';
            $item['operator_name'] = $item['operator']['nickname'] ?? '-';
            unset($item['user'], $item['operator']);
        }
        unset($item);

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
        $query = PointsLog::with(['user' => function ($q) {
            $q->field('id, nickname');
        }, 'operator' => function ($q) {
            $q->field('id, nickname');
        }])->where('user_id', $userId)->order('id', 'desc');

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        foreach ($list as &$item) {
            $item['user_nickname'] = $item['user']['nickname'] ?? '-';
            $item['operator_name'] = $item['operator']['nickname'] ?? '-';
            unset($item['user'], $item['operator']);
        }
        unset($item);

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
}
