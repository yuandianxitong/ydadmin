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
use core\base\Service;

class DashboardService extends Service
{
    protected AdminRepository $adminRepository;
    protected AdminLoginLogRepository $loginLogRepository;
    protected RoleRepository $roleRepository;
    protected MenuRepository $menuRepository;

    /**
     * 获取仪表板统计数据
     */
    public function getStats(): array
    {
        $adminCount = $this->adminRepository->count();
        $roleCount = $this->roleRepository->count();
        $menuCount = $this->menuRepository->count();
        $todayLoginCount = $this->loginLogRepository->getTodaySuccessCount();

        return [
            'adminCount'      => $adminCount,
            'roleCount'       => $roleCount,
            'menuCount'       => $menuCount,
            'todayLoginCount' => $todayLoginCount,
            'loginTrend'      => $this->loginLogRepository->getRecentTrend(7, true),
            'loginFailTrend'  => $this->loginLogRepository->getRecentTrend(7, false),
        ];
    }

    /**
     * 获取最近登录日志
     */
    public function getRecentLogs(int $limit = 10): array
    {
        return $this->loginLogRepository->getRecentLogs($limit);
    }
}
