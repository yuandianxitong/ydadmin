<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\AdminOperationLog;
use core\base\Repository;
use think\Model;

class AdminOperationLogRepository extends Repository
{
    protected function getModel(): Model
    {
        return new AdminOperationLog();
    }

    /**
     * 获取操作日志列表（支持复合搜索）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 15): array
    {
        $query = $this->model->order('id', 'desc');

        if (!empty($params['keyword'])) {
            $query->where('username|action|description', 'like', '%' . $params['keyword'] . '%');
        }

        if (!empty($params['method'])) {
            $query->where('method', '=', strtoupper($params['method']));
        }

        if (!empty($params['start_date'])) {
            $query->where('operation_time', '>=', $params['start_date']);
        }

        if (!empty($params['end_date'])) {
            $query->where('operation_time', '<=', $params['end_date'] . ' 23:59:59');
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 清空所有操作日志
     */
    public function clear(): bool
    {
        $this->model->where('id', '>', 0)->delete();
        return true;
    }
}
