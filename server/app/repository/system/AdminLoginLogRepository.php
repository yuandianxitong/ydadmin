<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\AdminLoginLog;
use core\base\Model;
use core\base\Repository;

/**
 * 管理员登录日志仓储。
 *
 * record() 复用基类 create()；browser/os 由 User-Agent 正则派生（parseBrowser/
 * parseOs，纯字符串匹配，无第三方库）。列表、趋势、排行随 M1b 的日志与仪表盘任务补齐。
 */
class AdminLoginLogRepository extends Repository
{
    protected function getModel(): Model
    {
        return new AdminLoginLog();
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
