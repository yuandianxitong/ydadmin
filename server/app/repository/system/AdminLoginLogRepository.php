<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\AdminLoginLog;
use core\base\Repository;
use think\Model;

class AdminLoginLogRepository extends Repository
{
    protected function getModel(): Model
    {
        return new AdminLoginLog();
    }

    /**
     * 获取登录日志列表（支持复合搜索）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 15): array
    {
        $query = $this->model->order('id', 'desc');

        if (!empty($params['keyword'])) {
            $query->where('username', 'like', '%' . $params['keyword'] . '%');
        }

        if (!empty($params['ip'])) {
            $query->where('ip', 'like', '%' . $params['ip'] . '%');
        }

        if (isset($params['login_result']) && $params['login_result'] !== '') {
            $query->where('login_result', '=', (int) $params['login_result']);
        }

        if (!empty($params['start_date'])) {
            $query->where('login_time', '>=', $params['start_date']);
        }

        if (!empty($params['end_date'])) {
            $query->where('login_time', '<=', $params['end_date'] . ' 23:59:59');
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
     * 今日登录成功次数
     */
    public function getTodaySuccessCount(): int
    {
        return $this->model->whereTime('login_time', 'today')
            ->where('login_result', 1)
            ->count();
    }

    /**
     * 最近N天登录趋势
     * @param int  $days    天数
     * @param bool $success true=成功趋势，false=失败趋势
     */
    public function getRecentTrend(int $days = 7, bool $success = true): array
    {
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $count = $this->model->whereDay('login_time', $date)
                ->where('login_result', $success ? 1 : 0)
                ->count();
            $trend[] = [
                'date'  => date('m-d', strtotime($date)),
                'count' => $count,
            ];
        }
        return $trend;
    }

    /**
     * 获取最近登录日志
     */
    public function getRecentLogs(int $limit = 10): array
    {
        return $this->model->order('login_time desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 清空所有登录日志
     */
    public function clear(): bool
    {
        $this->model->where('id', '>', 0)->delete();
        return true;
    }
}
