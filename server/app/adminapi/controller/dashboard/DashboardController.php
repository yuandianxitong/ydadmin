<?php

declare(strict_types=1);

namespace app\adminapi\controller\dashboard;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\DashboardService;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/** 仪表盘（契约 §2.11）。四个接口都是 #[PermissionSkip]：登录即可访问，数字按当前管理员的数据范围统计。 */
#[RouteGroup('/adminapi/dashboard')]
class DashboardController extends AuthenticatedController
{
    #[Inject]
    protected DashboardService $dashboardService;

    /** days：非数字（含空串）按默认 7，数字截断到 1..90（在 Service 里截断）。 */
    #[Get('/stats')]
    #[PermissionSkip]
    public function stats(Request $request): Response
    {
        $days = $request->get('days');

        return $this->success(
            $this->dashboardService->getStats(is_numeric($days) ? (int) $days : DashboardService::DEFAULT_DAYS),
            lang('messages.get_success')
        );
    }

    #[Get('/recent-logs')]
    #[PermissionSkip]
    public function recentLogs(): Response
    {
        return $this->success($this->dashboardService->getRecentLogs(), lang('messages.get_success'));
    }

    #[Get('/recent-activities')]
    #[PermissionSkip]
    public function recentActivities(): Response
    {
        return $this->success($this->dashboardService->getRecentActivities(), lang('messages.get_success'));
    }

    /** period 不传按 day；传了就必须是 day|week|month，否则 422（TP8 对非法值不报错，直接不限时间）。 */
    #[Get('/active-ranking')]
    #[PermissionSkip]
    public function activeRanking(Request $request): Response
    {
        $data = $this->validate(['period' => $request->get('period', 'day')], $this->activeRankingRules(), [
            'period.required' => 'validation.dashboard_period_invalid',
            'period.string'   => 'validation.dashboard_period_invalid',
            'period.in'       => 'validation.dashboard_period_invalid',
        ]);

        return $this->success($this->dashboardService->getActiveRanking((string) $data['period']), lang('messages.get_success'));
    }

    /** @return array<string, string> */
    private function activeRankingRules(): array
    {
        return ['period' => 'required|string|in:day,week,month'];
    }
}
