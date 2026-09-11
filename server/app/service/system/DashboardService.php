<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminLoginLogRepository;
use app\repository\system\AdminOperationLogRepository;
use app\repository\system\AdminRepository;
use app\repository\system\MenuRepository;
use app\repository\system\RoleRepository;
use app\repository\system\SystemConfigRepository;
use core\base\Service;
use core\context\RequestContext;
use DI\Attribute\Inject;
use support\Cache;

/**
 * 仪表盘（契约 §2.11，spec §6.4）。移植自 TP8 DashboardService，webman 写法参照 Saas TenantDashboardService。
 *
 * - 全部经 Repository 统计。管理员、登录日志、操作日志是受控表，按数据范围的管理员看到的是自己范围内的数字；
 *   角色、菜单、配置的数量不受数据权限约束。
 * - 每个接口的结果按管理员缓存 5 分钟，键为 dashboard.{接口}.{管理员 id}[.{参数}]。
 *   TP8 按 days 全站共用一份；接入数据权限后不同管理员的数字不同，必须分开。
 * - 缓存里只放与语言无关的数据：「最近动态」的文案与相对时间在取出缓存后按本次请求的语言生成。
 * - C 端用户字段（totalUsers、activeUsers、todayNewUsers 及其趋势、registerTrend）在 M5 会员模块接入前为 0 / []。
 */
class DashboardService extends Service
{
    public const DEFAULT_DAYS = 7;

    public const MAX_DAYS = 90;

    private const CACHE_TTL = 300;

    private const RECENT_LOG_LIMIT = 10;

    /** 最近动态：登录日志、操作日志各取最近 5 条，合并后按时间倒序取前 8 条（TP8 行为）。 */
    private const ACTIVITY_SOURCE_LIMIT = 5;

    private const ACTIVITY_LIMIT = 8;

    private const RANKING_LIMIT = 10;

    #[Inject]
    protected AdminRepository $adminRepository;

    #[Inject]
    protected RoleRepository $roleRepository;

    #[Inject]
    protected MenuRepository $menuRepository;

    #[Inject]
    protected SystemConfigRepository $systemConfigRepository;

    #[Inject]
    protected AdminLoginLogRepository $loginLogRepository;

    #[Inject]
    protected AdminOperationLogRepository $operationLogRepository;

    /**
     * 统计卡片与趋势。$days 截断到 1..90。
     *
     * @return array<string, mixed>
     */
    public function getStats(int $days = self::DEFAULT_DAYS): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));

        return $this->remember('stats', (string) $days, function () use ($days): array {
            // C 端用户（M5 接入前为 0）。保留与 TP8 相同的环比公式，M5 只需替换这几个变量的取值来源。
            $totalUsers = 0;
            $activeUsers = 0;
            $todayNewUsers = 0;
            $lastWeekNewUsers = 0;
            $lastWeekActiveUsers = 0;

            $todayLoginCount = $this->loginLogRepository->getTodaySuccessCount();
            $lastWeekLoginCount = $this->loginLogRepository->getLastWeekSameDaySuccessCount();

            return [
                'adminCount'        => $this->adminRepository->count(),
                'roleCount'         => $this->roleRepository->count(),
                'menuCount'         => $this->menuRepository->count(),
                'configCount'       => $this->systemConfigRepository->count(['status' => 1]),
                'todayLoginCount'   => $todayLoginCount,
                'todayNewUsers'     => $todayNewUsers,
                'activeUsers'       => $activeUsers,
                'totalUsers'        => $totalUsers,
                'trends'            => [
                    'totalUsers'      => $this->trend($totalUsers, $totalUsers - $todayNewUsers + $lastWeekNewUsers),
                    'activeUsers'     => $this->trendPercent($activeUsers, $lastWeekActiveUsers),
                    'todayNewUsers'   => $this->trend($todayNewUsers, $lastWeekNewUsers),
                    'todayLoginCount' => $this->trend($todayLoginCount, $lastWeekLoginCount),
                ],
                'operationLogCount' => $this->operationLogRepository->getTodayCount(),
                'loginTrend'        => $this->loginLogRepository->getRecentTrend($days),
                'registerTrend'     => [],
            ];
        });
    }

    /**
     * 最近 10 条登录日志（全字段）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(): array
    {
        return $this->remember('recent-logs', null, fn (): array => $this->loginLogRepository->getRecentLogs(self::RECENT_LOG_LIMIT));
    }

    /**
     * 最近动态。description 照 TP8 以用户名开头；登录类的固定文案与 relative_time 按本次请求的语言生成。
     *
     * @return list<array{type: string, username: string, description: string, time: string, relative_time: string}>
     */
    public function getRecentActivities(): array
    {
        $items = $this->remember('recent-activities', null, fn (): array => $this->collectActivities());

        $result = [];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? 'operation');
            $username = (string) ($item['username'] ?? '');
            $time = (string) ($item['time'] ?? '');
            $text = match ($type) {
                'login_success' => lang('messages.dashboard_login_success'),
                'login_failed'  => lang('messages.dashboard_login_failed'),
                default         => (string) ($item['detail'] ?? ''),
            };
            $result[] = [
                'type'          => $type,
                'username'      => $username,
                'description'   => $username . ' ' . $text,
                'time'          => $time,
                'relative_time' => $this->relativeTime($time),
            ];
        }

        return $result;
    }

    /**
     * 活跃排行：{period, list: [{rank, username, count}]}，前 10 名。$period 已由控制器校验为 day|week|month。
     *
     * @return array<string, mixed>
     */
    public function getActiveRanking(string $period): array
    {
        return $this->remember('active-ranking', $period, function () use ($period): array {
            $list = [];
            foreach ($this->loginLogRepository->getActiveRanking($period, self::RANKING_LIMIT) as $index => $row) {
                $list[] = ['rank' => $index + 1, 'username' => $row['username'], 'count' => $row['count']];
            }

            return ['period' => $period, 'list' => $list];
        });
    }

    /**
     * 合并两类日志，按时间倒序取前 8 条。只含与语言无关的字段，可以直接进缓存。
     *
     * @return list<array{type: string, username: string, detail: string, time: string}>
     */
    private function collectActivities(): array
    {
        $items = [];
        foreach ($this->loginLogRepository->getRecentLogs(self::ACTIVITY_SOURCE_LIMIT) as $log) {
            $items[] = [
                'type'     => (int) ($log['login_result'] ?? 0) === 1 ? 'login_success' : 'login_failed',
                'username' => (string) ($log['username'] ?? ''),
                'detail'   => '',
                'time'     => (string) ($log['login_time'] ?? ''),
            ];
        }
        foreach ($this->operationLogRepository->getRecentActivities(self::ACTIVITY_SOURCE_LIMIT) as $log) {
            $description = (string) ($log['description'] ?? '');
            $items[] = [
                'type'     => 'operation',
                'username' => (string) ($log['username'] ?? ''),
                'detail'   => $description !== '' ? $description : (string) ($log['action'] ?? ''),
                'time'     => (string) ($log['operation_time'] ?? ''),
            ];
        }
        // 两类时间都是 Y-m-d H:i:s，字符串比较即时间比较；usort 稳定，同一秒时登录在前
        usort($items, static fn (array $a, array $b): int => strcmp($b['time'], $a['time']));

        return array_slice($items, 0, self::ACTIVITY_LIMIT);
    }

    /**
     * 按管理员缓存 5 分钟。管理员 id 取自本次请求（RequestContext），不存在实例属性上。
     *
     * @param \Closure(): array<mixed> $compute
     * @return array<mixed>
     */
    private function remember(string $endpoint, ?string $param, \Closure $compute): array
    {
        $key = 'dashboard.' . $endpoint . '.' . RequestContext::actingUser() . ($param !== null ? '.' . $param : '');
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $value = $compute();
        Cache::set($key, $value, self::CACHE_TTL);

        return $value;
    }

    /** 相对时间，分档照 TP8 DateHelper::diffForHumans（刚刚 / N 分钟前 / N 小时前 / N 天前 / N 个月前 / 更早）。 */
    private function relativeTime(string $time): string
    {
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            return '';
        }
        $diff = max(0, time() - $timestamp);

        return match (true) {
            $diff < 60       => lang('messages.time_just_now'),
            $diff < 3600     => lang('messages.time_minutes_ago', ['count' => (string) intdiv($diff, 60)]),
            $diff < 86400    => lang('messages.time_hours_ago', ['count' => (string) intdiv($diff, 3600)]),
            $diff < 2592000  => lang('messages.time_days_ago', ['count' => (string) intdiv($diff, 86400)]),
            $diff < 31536000 => lang('messages.time_months_ago', ['count' => (string) intdiv($diff, 2592000)]),
            default          => lang('messages.time_long_ago'),
        };
    }

    /**
     * 环比（绝对差）。
     *
     * @return array{value: int, type: string}
     */
    private function trend(int $current, int $previous): array
    {
        $diff = $current - $previous;

        return ['value' => abs($diff), 'type' => $diff >= 0 ? 'up' : 'down'];
    }

    /**
     * 环比（百分比，保留两位小数）；上期为 0 时按 TP8：本期大于 0 记 100%，否则 0%。
     *
     * @return array{value: int|float, type: string, unit: string}
     */
    private function trendPercent(int $current, int $previous): array
    {
        if ($previous === 0) {
            return ['value' => $current > 0 ? 100 : 0, 'type' => 'up', 'unit' => 'percent'];
        }
        $percent = round(($current - $previous) / $previous * 100, 2);

        return ['value' => abs($percent), 'type' => $percent >= 0 ? 'up' : 'down', 'unit' => 'percent'];
    }
}
