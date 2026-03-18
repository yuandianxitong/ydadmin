<?php
declare(strict_types=1);

namespace app\repository\user;

use app\model\user\BalanceLog;
use core\base\Model;
use core\base\Repository;

class BalanceLogRepository extends Repository
{
    protected function getModel(): Model
    {
        return new BalanceLog();
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = BalanceLog::order('id', 'desc');
        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        if (!empty($params['user_ids'])) {
            $query->whereIn('user_id', $params['user_ids']);
        }
        if (!empty($params['start_time'])) {
            $query->where('created_at', '>=', $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('created_at', '<=', $params['end_time']);
        }
        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    public function getUserLogs(int $userId, int $page = 1, int $limit = 10): array
    {
        $query = BalanceLog::where('user_id', $userId)->order('id', 'desc');
        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    public function existsBySource(string $source): bool
    {
        return BalanceLog::where('source', $source)->count() > 0;
    }
}
