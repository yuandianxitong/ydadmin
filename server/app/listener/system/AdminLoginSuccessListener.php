<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\system;

use app\model\system\AdminLoginLog;
use app\repository\system\AdminRepository;

/**
 * 管理员登录成功监听器
 *
 * 事件数据：
 * - admin_id: int       管理员ID
 * - username: string    用户名
 * - ip: string          登录IP
 * - user_agent: string  浏览器UA
 */
class AdminLoginSuccessListener
{
    public function handle(array $event): void
    {
        // 更新最后登录信息
        app()->make(AdminRepository::class)
            ->updateLastLogin((int)$event['admin_id'], $event['ip']);

        // 记录登录成功日志
        AdminLoginLog::record([
            'admin_id'      => $event['admin_id'],
            'username'       => $event['username'],
            'ip'             => $event['ip'],
            'user_agent'     => $event['user_agent'],
            'login_result'   => true,
            'login_message'  => lang('messages.login_success'),
        ]);
    }
}
