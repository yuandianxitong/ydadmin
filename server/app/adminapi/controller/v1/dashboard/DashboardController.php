<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\dashboard;

use app\service\system\DashboardService;
use core\base\Controller;
use think\Response;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '仪表板', description: '仪表板统计数据和最近日志')]
class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    #[PermissionSkip]
    #[OA\Get(
        path: '/dashboard/stats',
        summary: '获取仪表板统计数据',
        security: [['bearerAuth' => []]],
        tags: ['仪表板'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 400, description: '请求失败')
        ]
    )]
    public function stats(): Response
    {
        try {
            $data = $this->dashboardService->getStats();
            return $this->success(lang('messages.get_success'), $data);
        } catch (\Exception $e) {
            return $this->error(sprintf(lang('business.get_stats_failed'), $e->getMessage()));
        }
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/dashboard/recent-logs',
        summary: '获取最近登录日志',
        security: [['bearerAuth' => []]],
        tags: ['仪表板'],
        responses: [
            new OA\Response(response: 200, description: '获取成功'),
            new OA\Response(response: 400, description: '请求失败')
        ]
    )]
    public function recentLogs(): Response
    {
        try {
            $logs = $this->dashboardService->getRecentLogs();
            return $this->success(lang('messages.get_success'), $logs);
        } catch (\Exception $e) {
            return $this->error(sprintf(lang('business.get_login_log_failed'), $e->getMessage()));
        }
    }
}
