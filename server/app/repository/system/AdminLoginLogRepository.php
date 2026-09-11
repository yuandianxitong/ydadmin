<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\AdminLoginLog;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 管理员登录日志仓储。
 *
 * record() 复用基类 create()；browser/os 由 User-Agent 正则派生（parseBrowser/
 * parseOs，纯字符串匹配，无第三方库）。受数据权限约束（owner 列 admin_id，spec §5.5）；
 * 登录时尚无当前管理员，record() 不受影响。
 * 仪表盘用的统计、趋势、排行方法同样经 query()，按数据范围统计。
 */
class AdminLoginLogRepository extends Repository
{
    protected bool $dataScoped = true;

    protected string $ownerColumn = 'admin_id';

    /** 登录日志表没有 created_by。 */
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new AdminLoginLog();
    }

    /**
     * @param array<string, mixed> $params keyword（username 模糊）、ip（模糊）、login_result（0/1）、start_date、end_date（Y-m-d）
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getSearchList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('username'), 'like', Like::contains($keyword));
        }
        $ip = trim((string) ($params['ip'] ?? ''));
        if ($ip !== '') {
            $query->where($this->qualify('ip'), 'like', Like::contains($ip));
        }
        if (isset($params['login_result']) && $params['login_result'] !== '') {
            $query->where($this->qualify('login_result'), (int) $params['login_result']);
        }
        if (!empty($params['start_date'])) {
            $query->where($this->qualify('login_time'), '>=', (string) $params['start_date'] . ' 00:00:00');
        }
        if (!empty($params['end_date'])) {
            $query->where($this->qualify('login_time'), '<=', (string) $params['end_date'] . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('id'), 'desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /** 删除当前管理员数据范围内可见的全部登录日志（不软删，一条 DELETE）。 @return int 删除条数 */
    public function clearVisible(): int
    {
        return (int) $this->query()->delete();
    }

    /**
     * 写入一条登录日志。
     *
     * @param array<string, mixed> $data
     */
    public function record(array $data): void
    {
        $userAgent = (string) ($data['user_agent'] ?? '');

        $this->create([
            'admin_id'      => $data['admin_id'] ?? 0,
            'username'      => $data['username'] ?? '',
            'ip'            => $data['ip'] ?? '',
            'user_agent'    => $userAgent,
            'login_time'    => date('Y-m-d H:i:s'),
            'login_result'  => !empty($data['login_result']) ? 1 : 0,
            'login_message' => $data['login_message'] ?? '',
            'browser'       => $this->parseBrowser($userAgent),
            'os'            => $this->parseOs($userAgent),
        ]);
    }

    /** 今日登录成功次数（按数据范围，仪表盘用）。 */
    public function getTodaySuccessCount(): int
    {
        $today = new \DateTimeImmutable('today');

        return $this->countSuccessBetween($today, $today->add(new \DateInterval('P1D')));
    }

    /** 上周同日（7 天前那一天）登录成功次数，仪表盘环比用。 */
    public function getLastWeekSameDaySuccessCount(): int
    {
        $day = (new \DateTimeImmutable('today'))->sub(new \DateInterval('P7D'));

        return $this->countSuccessBetween($day, $day->add(new \DateInterval('P1D')));
    }

    /**
     * 最近 N 天（含今天）每天的登录成功次数：一次 GROUP BY，PHP 侧补零；date 为 m-d（与 TP8 一致）。
     *
     * @return list<array{date: string, count: int}>
     */
    public function getRecentTrend(int $days): array
    {
        $days = max(1, $days);
        $today = new \DateTimeImmutable('today');
        $start = $today->sub(new \DateInterval('P' . ($days - 1) . 'D'));

        $rows = $this->query()
            ->where('login_result', 1)
            ->where('login_time', '>=', $start->format('Y-m-d H:i:s'))
            ->toBase() // toBase() 先应用全局作用域（数据权限）再取底层查询，结果不经模型的 casts/appends；
            // 放在 selectRaw()/groupBy() 之前调用：这两个方法未在 Eloquent\Builder 上显式声明，
            // 经 @mixin 转发解析出的返回类型是 Query\Builder，此时再调用 toBase() 会被 PHPStan 判定为未定义方法
            ->selectRaw('DATE(login_time) AS log_date, COUNT(*) AS total')
            ->groupBy('log_date')
            ->get();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->log_date] = (int) $row->total;
        }

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->sub(new \DateInterval('P' . $i . 'D'));
            $trend[] = ['date' => $day->format('m-d'), 'count' => $counts[$day->format('Y-m-d')] ?? 0];
        }

        return $trend;
    }

    /**
     * 最近的登录日志（全字段，按登录时间倒序）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 10): array
    {
        return $this->query()->orderByDesc('login_time')->orderByDesc('id')->limit($limit)->get()->toArray();
    }

    /**
     * 按登录成功次数排行（period：day 今天、week 本周一起、month 本月一日起）。按用户名分组，与 TP8 一致。
     *
     * @return list<array{username: string, count: int}>
     */
    public function getActiveRanking(string $period, int $limit = 10): array
    {
        $since = match ($period) {
            'week'  => (new \DateTimeImmutable('monday this week'))->format('Y-m-d 00:00:00'),
            'month' => date('Y-m-01 00:00:00'),
            default => date('Y-m-d 00:00:00'),
        };

        $rows = $this->query()
            ->where('login_result', 1)
            ->where('login_time', '>=', $since)
            ->toBase() // 先于 selectRaw()/groupBy() 调用，理由同 getRecentTrend()
            ->selectRaw('username, COUNT(*) AS total')
            ->groupBy('username')
            ->orderByDesc('total')
            ->orderBy('username')
            ->limit($limit)
            ->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['username' => (string) $row->username, 'count' => (int) $row->total];
        }

        return $list;
    }

    /** [开始, 结束) 区间内的登录成功次数；用区间比较而不是 whereDate()，能用上 login_time 索引。 */
    private function countSuccessBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return $this->query()
            ->where('login_result', 1)
            ->where('login_time', '>=', $from->format('Y-m-d H:i:s'))
            ->where('login_time', '<', $to->format('Y-m-d H:i:s'))
            ->count();
    }

    /** 解析浏览器（纯字符串/正则匹配，无第三方库）。 */
    protected function parseBrowser(string $userAgent): string
    {
        $browsers = [
            'Chrome'  => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari'  => '/Safari\/([0-9.]+)/',
            'Edge'    => '/Edge\/([0-9.]+)/',
            'Opera'   => '/Opera\/([0-9.]+)/',
            'IE'      => '/MSIE ([0-9.]+)/',
        ];

        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return $browser . ' ' . $matches[1];
            }
        }

        return 'Unknown';
    }

    /** 解析操作系统（纯字符串/正则匹配，无第三方库）。 */
    protected function parseOs(string $userAgent): string
    {
        $systems = [
            'Windows 10'  => '/Windows NT 10.0/',
            'Windows 8.1' => '/Windows NT 6.3/',
            'Windows 8'   => '/Windows NT 6.2/',
            'Windows 7'   => '/Windows NT 6.1/',
            'Mac OS X'    => '/Mac OS X ([0-9_]+)/',
            'Linux'       => '/Linux/',
            'Android'     => '/Android ([0-9.]+)/',
            'iOS'         => '/iPhone OS ([0-9_]+)/',
        ];

        foreach ($systems as $system => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $system;
            }
        }

        return 'Unknown';
    }
}
