<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use think\model\relation\BelongsTo;

class AdminLoginLog extends Model
{
    protected $table = 'admin_login_logs';

    // 日志表不需要自动更新时间和软删除
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $fillable = [
        'admin_id', 'username', 'ip', 'user_agent', 'login_time',
        'login_result', 'login_message', 'browser', 'os'
    ];

    protected $type = [
        'admin_id' => 'integer',
        'login_result' => 'boolean',
        'login_time' => 'datetime',
    ];

    protected $append = ['login_result_text'];

    // 关联管理员
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    // 访问器
    public function getLoginResultTextAttr($value, $data): string
    {
        if (!isset($data['login_result'])) {
            return '';
        }
        return $data['login_result'] ? '成功' : '失败';
    }

    /**
     * 记录登录日志
     */
    public static function record(array $data): void
    {
        // 解析User-Agent
        $userAgent = $data['user_agent'] ?? '';
        $browser = self::parseBrowser($userAgent);
        $os = self::parseOs($userAgent);

        self::create([
            'admin_id' => $data['admin_id'] ?? 0,
            'username' => $data['username'] ?? '',
            'ip' => $data['ip'] ?? '',
            'user_agent' => $userAgent,
            'login_time' => date('Y-m-d H:i:s'),
            'login_result' => $data['login_result'] ?? false,
            'login_message' => $data['login_message'] ?? '',
            'browser' => $browser,
            'os' => $os,
        ]);
    }

    /**
     * 解析浏览器
     */
    protected static function parseBrowser(string $userAgent): string
    {
        $browsers = [
            'Chrome' => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari' => '/Safari\/([0-9.]+)/',
            'Edge' => '/Edge\/([0-9.]+)/',
            'Opera' => '/Opera\/([0-9.]+)/',
            'IE' => '/MSIE ([0-9.]+)/',
        ];

        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return $browser . ' ' . $matches[1];
            }
        }

        return 'Unknown';
    }

    /**
     * 解析操作系统
     */
    protected static function parseOs(string $userAgent): string
    {
        $systems = [
            'Windows 10' => '/Windows NT 10.0/',
            'Windows 8.1' => '/Windows NT 6.3/',
            'Windows 8' => '/Windows NT 6.2/',
            'Windows 7' => '/Windows NT 6.1/',
            'Mac OS X' => '/Mac OS X ([0-9_]+)/',
            'Linux' => '/Linux/',
            'Android' => '/Android ([0-9.]+)/',
            'iOS' => '/iPhone OS ([0-9_]+)/',
        ];

        foreach ($systems as $system => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $system;
            }
        }

        return 'Unknown';
    }
}
