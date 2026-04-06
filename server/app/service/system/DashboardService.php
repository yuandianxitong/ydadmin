<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminRepository;
use app\repository\system\AdminLoginLogRepository;
use app\repository\system\RoleRepository;
use app\repository\system\MenuRepository;
use app\repository\user\UserRepository;
use app\repository\system\AdminOperationLogRepository;
use app\repository\system\SystemConfigRepository;
use core\base\Service;
use core\helper\DateHelper;
use think\facade\Cache;

class DashboardService extends Service
{
    protected AdminRepository $adminRepository;
    protected AdminLoginLogRepository $loginLogRepository;
    protected RoleRepository $roleRepository;
    protected MenuRepository $menuRepository;
    protected UserRepository $userRepository;
    protected AdminOperationLogRepository $operationLogRepository;
    protected SystemConfigRepository $systemConfigRepository;

    /**
     * 获取仪表板统计数据（5分钟缓存）
     * @param int $days 趋势天数（7=本周, 30=本月）
     */
    public function getStats(int $days = 7): array
    {
        $cacheKey = 'dashboard_stats_' . $days;
        return Cache::remember($cacheKey, function () use ($days) {
            $totalUsers = $this->userRepository->getTotalCount();
            $activeUsers = $this->userRepository->getActiveCount(7);
            $todayNewUsers = $this->userRepository->getTodayNewCount();
            $todayLoginCount = $this->loginLogRepository->getTodaySuccessCount();

            // 环比趋势（与上周同日对比）
            $lastWeekNewUsers = $this->userRepository->getLastWeekSameDayNewCount();
            $lastWeekLogin = $this->loginLogRepository->getLastWeekSameDaySuccessCount();
            $lastWeekActive = $this->userRepository->getLastWeekActiveCount();

            return [
                'adminCount'       => $this->adminRepository->count(),
                'roleCount'        => $this->roleRepository->count(),
                'menuCount'        => $this->menuRepository->count(),
                'configCount'      => $this->systemConfigRepository->getTotalCount(),
                'todayLoginCount'  => $todayLoginCount,
                'todayNewUsers'    => $todayNewUsers,
                'activeUsers'      => $activeUsers,
                'totalUsers'       => $totalUsers,
                'trends' => [
                    'totalUsers'      => $this->calcTrend($totalUsers, $totalUsers - $todayNewUsers + $lastWeekNewUsers),
                    'activeUsers'     => $this->calcTrendPercent($activeUsers, $lastWeekActive),
                    'todayNewUsers'   => $this->calcTrend($todayNewUsers, $lastWeekNewUsers),
                    'todayLoginCount' => $this->calcTrend($todayLoginCount, $lastWeekLogin),
                ],
                'operationLogCount' => $this->operationLogRepository->getTodayCount(),
                'loginTrend'       => $this->loginLogRepository->getRecentTrend($days, true),
                'registerTrend'    => $this->userRepository->getRegisterTrend($days),
            ];
        }, 300);
    }

    /**
     * 获取最近登录日志
     */
    public function getRecentLogs(int $limit = 10): array
    {
        return $this->loginLogRepository->getRecentLogs($limit);
    }

    /**
     * 获取最近动态（合并登录日志 + 操作日志）
     */
    public function getRecentActivities(int $limit = 8): array
    {
        $loginLogs = $this->loginLogRepository->getRecentLogs(5);
        $operationLogs = $this->operationLogRepository->getRecentActivities(5);

        $activities = [];

        foreach ($loginLogs as $log) {
            $activities[] = [
                'type'          => $log['login_result'] ? 'login_success' : 'login_failed',
                'username'      => $log['username'],
                'description'   => $log['username'] . ($log['login_result'] ? ' 登录系统' : ' 登录失败'),
                'time'          => $log['login_time'],
                'relative_time' => DateHelper::diffForHumans($log['login_time']),
            ];
        }

        foreach ($operationLogs as $log) {
            $activities[] = [
                'type'          => 'operation',
                'username'      => $log['username'],
                'description'   => $log['username'] . ' ' . ($log['description'] ?: $log['action']),
                'time'          => $log['operation_time'],
                'relative_time' => DateHelper::diffForHumans($log['operation_time']),
            ];
        }

        usort($activities, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));

        return array_slice($activities, 0, $limit);
    }

    /**
     * 获取活跃排行
     */
    public function getActiveRanking(string $period = 'day', int $limit = 10): array
    {
        $list = $this->loginLogRepository->getActiveRanking($period, $limit);

        $ranked = [];
        foreach ($list as $index => $item) {
            $ranked[] = [
                'rank'     => $index + 1,
                'username' => $item['username'],
                'count'    => (int) $item['count'],
            ];
        }

        return [
            'period' => $period,
            'list'   => $ranked,
        ];
    }

    /**
     * 计算趋势（绝对值差）
     */
    private function calcTrend(int $current, int $previous): array
    {
        $diff = $current - $previous;
        return [
            'value' => abs($diff),
            'type'  => $diff >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * 计算趋势（百分比）
     */
    private function calcTrendPercent(int $current, int $previous): array
    {
        if ($previous === 0) {
            return ['value' => $current > 0 ? 100 : 0, 'type' => 'up', 'unit' => 'percent'];
        }
        $percent = round(($current - $previous) / $previous * 100, 2);
        return [
            'value' => abs($percent),
            'type'  => $percent >= 0 ? 'up' : 'down',
            'unit'  => 'percent',
        ];
    }

}
